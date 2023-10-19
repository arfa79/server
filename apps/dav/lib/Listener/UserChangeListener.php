<?php

declare(strict_types=1);

/**
 * @copyright 2023 Thomas Citharel <nextcloud@tcit.fr>
 *
 * @author Thomas Citharel <nextcloud@tcit.fr>
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
namespace OCA\DAV\Listener;

use OCA\DAV\BackgroundJob\SyncSystemAddressBookAfterUsersChange;
use OCP\Accounts\UserUpdatedEvent;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;

/**
 * @template-implements IEventListener<UserUpdatedEvent|UserAddedEvent|UserRemovedEvent>
 */
class UserChangeListener implements IEventListener {
	private IJobList $jobList;

	public function __construct(IJobList $jobList) {
		$this->jobList = $jobList;
	}

	public function handle(Event $event): void {
		if (!($event instanceof UserUpdatedEvent || $event instanceof UserAddedEvent || $event instanceof UserRemovedEvent)) {
			// Not what we subscribed to
			return;
		}
		$this->jobList->add(SyncSystemAddressBookAfterUsersChange::class, [$event->getUser()]);
	}
}
