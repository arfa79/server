<?php

declare(strict_types=1);

/**
 * @copyright 2023 Benjamin Gaussorgues <benjamin.gaussorgues@nextcloud.com>
 *
 * @author Benjamin Gaussorgues <benjamin.gaussorgues@nextcloud.com>
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

use OCP\Search\IFilter;
use OCP\Search\Filter;
use OCP\IGroupManager;
use OCP\IUserManager;
use RuntimeException;

final class FilterFactory {
	public static function get(string $type, string|array $filter): IFilter {
		return match ($type) {
			'boolean' => new Filter\BooleanFilter($filter),
			'datetime' => new Filter\DateTimeFilter($filter),
			'float' => new Filter\FloatFilter($filter),
			'group' => new Filter\GroupFilter($filter, \OC\Server::get(IGroupManager::class)),
			'integer' => new Filter\IntegerFilter($filter),
			'person' => self::getPerson($filter),
			'string' => new Filter\StringFilter($filter),
			'strings' => new Filter\StringsFilter(... (array) $filter),
			'user' => new Filter\UserFilter($filter, \OC\Server::get(IUserManager::class)),
			default => throw new RuntimeException('Invalid filter type '. $type),
		};
	}

	private static function getPerson(string $person): IFilter {
		[$type, $filter] = explode('_', $person, 2);

		return self::get($type, $filter, $multiple);
	}
}
