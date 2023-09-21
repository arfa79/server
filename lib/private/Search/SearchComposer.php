<?php

declare(strict_types=1);

/**
 * @copyright 2020 Christoph Wurst <christoph@winzerhof-wurst.at>
 *
 * @author Christoph Wurst <christoph@winzerhof-wurst.at>
 * @author Joas Schilling <coding@schilljs.com>
 * @author John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OC\Search;

use InvalidArgumentException;
use OC\AppFramework\Bootstrap\Coordinator;
use OCP\IUser;
use OCP\Search\FilterCollection;
use OCP\Search\IFilter;
use OCP\Search\IProvider;
use OCP\Search\IProviderV2;
use OCP\Search\ISearchQuery;
use OCP\Search\SearchResult;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use function array_map;

/**
 * Queries individual \OCP\Search\IProvider implementations and composes a
 * unified search result for the user's search term
 *
 * The search process is generally split into two steps
 *
 *   1. Get a list of provider (`getProviders`)
 *   2. Get search results of each provider (`search`)
 *
 * The reasoning behind this is that the runtime complexity of a combined search
 * result would be O(n) and linearly grow with each provider added. This comes
 * from the nature of php where we can't concurrently fetch the search results.
 * So we offload the concurrency the client application (e.g. JavaScript in the
 * browser) and let it first get the list of providers to then fetch all results
 * concurrently. The client is free to decide whether all concurrent search
 * results are awaited or shown as they come in.
 *
 * @see IProvider::search() for the arguments of the individual search requests
 */
class SearchComposer {
	/**
	 * @var IProvider[]
	 */
	private array $providers = [];

	private const FILTERS = [
		'term' => 'string',
		'since' => 'datetime',
		'until' => 'datetime',
		'title-only' => 'boolean',
		'person' => 'person',
		'places' => 'strings',
		'provider' => 'string',
	];
	private array $customFilters = [];

	private array $handlers = [];

	public function __construct(
		private Coordinator $bootstrapCoordinator,
		private ContainerInterface $container,
		private LoggerInterface $logger
	) {
	}

	/**
	 * Load all providers dynamically that were registered through `registerProvider`
	 *
	 * If $targetProviderId is provided, only this provider is loaded
	 * If a provider can't be loaded we log it but the operation continues nevertheless
	 */
	private function loadLazyProviders(?string $targetProviderId = null): void {
		$context = $this->bootstrapCoordinator->getRegistrationContext();
		if ($context === null) {
			// Too early, nothing registered yet
			return;
		}

		$registrations = $context->getSearchProviders();
		foreach ($registrations as $registration) {
			try {
				/** @var IProvider $provider */
				$provider = $this->container->get($registration->getService());
				$providerId = $provider->getId();
				if ($targetProviderId !== null && $targetProviderId !== $providerId) {
					continue;
				}
				$this->providers[$providerId] = $provider;
				$this->handlers[$providerId] = [$providerId];
				if ($targetProviderId !== null) {
					break;
				}
			} catch (ContainerExceptionInterface $e) {
				// Log an continue. We can be fault tolerant here.
				$this->logger->error('Could not load search provider dynamically: ' . $e->getMessage(), [
					'exception' => $e,
					'app' => $registration->getAppId(),
				]);
			}
		}

		$this->loadFilters();
	}

	private function loadFilters(): void {
		foreach ($this->providers as $providerId => $provider) {
			if (!$provider instanceof IProviderV2) {
				continue;
			}

			foreach ($provider->registerCustomFilters() as $name => $filter) {
				$this->registerCustomFilter($name, $filter, $provider->getId());
			}
			foreach ($provider->getAlternateIds() as $alternateId) {
				$this->handlers[$alternateId][] = $providerId;
			}
			foreach ($provider->getSupportedFilters() as $filterName) {
				if (!isset(self::FILTERS[$filterName]) && !isset($this->customFilters[$providerId][$filterName])) {
					throw new InvalidArgumentException('Invalid filter '. $filterName);
				}
			}
		}
	}

	private function registerCustomFilter(string $name, string $filter, string $providerId): void {
		if (!preg_match('/[-0-9a-z]+/Au', $name)) {
			throw new InvalidArgumentException('Invalid filter name. Allowed characters are [-0-9a-z]');
		}

		if (isset($this->customFilters[$providerId])) {
			$this->customFilters[$providerId][$name] = $filter;
		} else {
			$this->customFilters[$providerId] = [$name => $filter];
		}
	}

	/**
	 * Get a list of all provider IDs & Names for the consecutive calls to `search`
	 * Sort the list by the order property
	 *
	 * @param string $route the route the user is currently at
	 * @param array $routeParameters the parameters of the route the user is currently at
	 *
	 * @return array
	 */
	public function getProviders(string $route, array $routeParameters): array {
		$this->loadLazyProviders();

		$providers = array_values(
			array_map(function (IProvider $provider) use ($route, $routeParameters) {
				$triggers = [$provider->getId()];
				if ($provider instanceof IProviderV2) {
					$triggers = $provider->getAlternateIds();
					$triggers[] = $provider->getId();
					$filters = $provider->getSupportedFilters();
				} else {
					$triggers = [$provider->getId()];
					$filters = ['term'];
				}

				return [
					'id' => $provider->getId(),
					'name' => $provider->getName(),
					'order' => $provider->getOrder($route, $routeParameters),
					'triggers' => $triggers,
					'filters' => $this->getFiltersType($filters, $provider->getId()),
				];
			}, $this->providers)
		);

		usort($providers, function ($provider1, $provider2) {
			return $provider1['order'] <=> $provider2['order'];
		});

		/**
		 * Return an array with the IDs, but strip the associative keys
		 */
		return $providers;
	}

	/**
	 * @param $filters string[]
	 * @return array{string, string}
	 */
	private function getFiltersType(array $filters, string $providerId): array {
		$filterList = [];
		foreach ($filters as $filter) {
			$filterList[$filter] = $this->getFilterType($filter, $providerId);
		}

		return $filterList;
	}

	private function getFilterType(string $filter, string $providerId): ?string {
		return match (true) {
			isset(self::FILTERS[$filter]) => self::FILTERS[$filter],
			isset($this->customFilters[$providerId][$filter]) => $this->customFilters[$providerId][$filter],
			default => null,
		};
	}

	public function buildFilterList(string $providerId, array $filters): FilterCollection {
		$this->loadLazyProviders($providerId);

		$list = [];
		foreach ($filters as $filter => $value) {
			$list[$filter] = $this->buildFilter($filter, $value, $providerId);
		}

		return new FilterCollection(... array_filter($list));
	}

	private function buildFilter(string $filter, string $value, string $providerId): ?IFilter {
		$filterType = $this->getFilterType($filter, $providerId);
		if ($filterType === null) {
			$this->logger->debug('Unable to find {filter} definition', [
				'filter' => $filter,
				'value' => $value,
			]);

			return null;
		}

		return FilterFactory::get($filterType, $value);
	}

	/**
	 * Query an individual search provider for results
	 *
	 * @param IUser $user
	 * @param string $providerId one of the IDs received by `getProviders`
	 * @param ISearchQuery $query
	 *
	 * @return SearchResult
	 * @throws InvalidArgumentException when the $providerId does not correspond to a registered provider
	 */
	public function search(
		IUser $user,
		string $providerId,
		ISearchQuery $query,
	): SearchResult {
		$this->loadLazyProviders($providerId);

		$provider = $this->providers[$providerId] ?? null;
		if ($provider === null) {
			throw new InvalidArgumentException("Provider $providerId is unknown");
		}

		return $provider->search($user, $query);
	}
}
