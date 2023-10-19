<?php

declare(strict_types=1);

/**
 * @copyright 2022 Thomas Citharel <nextcloud@tcit.fr>
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
namespace OCA\DAV\Tests\Unit\Listener;

use OCA\DAV\BackgroundJob\SyncSystemAddressBookAfterUsersChange;
use OCA\DAV\Listener\UserChangeListener;
use OCP\Accounts\UserUpdatedEvent;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\Event;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IGroup;
use OCP\IUser;
use OCP\User\Events\PostLoginEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UserChangeListenerTest extends TestCase {
	private IJobList|MockObject $jobList;
	private UserChangeListener $listener;

	protected function setUp(): void {
		parent::setUp();

		$this->jobList = $this->createMock(IJobList::class);

		$this->listener = new UserChangeListener(
			$this->jobList
		);
	}

	/**
	 * @dataProvider dataForTestHandleUserChangeEvent
	 */
	public function testHandleUserChangeEvent(Event $event, IUser $user, bool $willSync): void {
		$this->jobList->expects($willSync ? $this->once() : $this->never())->method('add')->with(SyncSystemAddressBookAfterUsersChange::class, [$user]);
		$this->listener->handle($event);
	}

	public function dataForTestHandleUserChangeEvent(): array {
		$group = $this->createMock(IGroup::class);
		$user = $this->createMock(IUser::class);
		return [
			[new UserAddedEvent($group, $user), $user, true],
			[new UserUpdatedEvent($user, []), $user, true],
			[new UserRemovedEvent($group, $user), $user, true],
			[new PostLoginEvent($user, 'a', 'b', true), $user, false],
		];
	}
}
