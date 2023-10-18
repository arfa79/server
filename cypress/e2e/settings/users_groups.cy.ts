/**
 * @copyright 2023 Christopher Ng <chrng8@gmail.com>
 *
 * @author Christopher Ng <chrng8@gmail.com>
 *
 * @license AGPL-3.0-or-later
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

import { User } from '@nextcloud/cypress'
// eslint-disable-next-line n/no-extraneous-import
import randomString from 'crypto-random-string'

const admin = new User('admin', 'admin')

describe('Settings: Create and delete groups', () => {
	beforeEach(() => {
		cy.login(admin)
		cy.visit('/settings/users')
	})

	it('Can create a group', () => {
		const groupName = randomString(7)
		// open the Create group menu
		cy.get('button[aria-label="Create group"]').click()

		cy.get('.action-item__popper ul[role="menu"]').within(() => {
			// see that the group name is ""
			cy.get('input[placeholder="Group name"]').should('exist').and('have.value', '')
			// set the group name to foo
			cy.get('input[placeholder="Group name"]').type(groupName)
			// see that the group name is foo
			cy.get('input[placeholder="Group name"]').should('have.value', groupName)
			// submit the group name
			cy.get('input[placeholder="Group name"] ~ button').click()
		})

		// Ignore failure if modal is not shown
		cy.once('fail', (error) => {
			expect(error.name).to.equal('AssertionError')
			expect(error).to.have.property('node', '.modal-container')
		})
		// Make sure no confirmation modal is shown
		cy.get('body').find('.modal-container').then(($modals) => {
			if ($modals.length > 0) {
				cy.wrap($modals.first()).find('input[type="password"]').type(admin.password)
				cy.wrap($modals.first()).find('button').contains('Confirm').click()
			}
		})

		// see that the created group is in the list
		cy.get('ul.app-navigation__list').within(() => {
			// see that the list of groups contains the group foo
			cy.contains(groupName).should('exist')
		})
	})

	describe('Assign user to a group', () => {
		const groupName = randomString(7)
		let testUser: User

		before(() => {
			cy.createRandomUser().then((user) => {
				testUser = user
			})
			cy.runOccCommand(`group:add '${groupName}'`)
			cy.reload()
		})

		it('see that the group is in the list', () => {
			cy.get('ul.app-navigation__list').contains('li', groupName).should('exist')
			cy.get('ul.app-navigation__list')
				.contains('li', groupName)
				.find('.counter-bubble__counter')
				.should('contain', '0')
		})

		it('see that the user is in the list', () => {
			cy.get(`[data-test="${testUser.userId}"]`)
				.contains(testUser.userId)
				.should('exist')
				.scrollIntoView()
		})

		it('switch into user edit mode', () => {
			cy.get(`[data-test="${testUser.userId}"]`)
				.find('.row__cell--actions')
				.find('button[aria-label="Edit"]')
				.click({ force: true })
			cy.get(`[data-test="${testUser.userId}"]`)
				.find('[data-cy-cell="groups"] input')
				.should('exist')
		})

		it('assign the group', () => {
			// focus inside the input
			cy.get(`[data-test="${testUser.userId}"]`)
				.find('[data-cy-cell="groups"] input')
				.click({ force: true })
			// enter the group name
			cy.get(`[data-test="${testUser.userId}"]`)
				.find('[data-cy-cell="groups"] input')
				.type(`${groupName}`)
			cy.contains('li.vs__dropdown-option', groupName)
				.click({ force: true })
		})

		it('see the group was successfully assigned', () => {
			// wait for it to be ready
			cy.get(`[data-test="${testUser.userId}"]`)
				.find('[data-cy-cell="groups"] .loading-icon')
				.should('exist')
			cy.waitUntil(() => {
				cy.get(`[data-test="${testUser.userId}"]`)
					.find('[data-cy-cell="groups"] .loading-icon')
					.should('not.be.visible')
			}, { timeout: 10000 })

			// see a new memeber
			cy.get('ul.app-navigation__list')
				.contains('li', groupName)
				.find('.counter-bubble__counter')
				.should('contain', '0')
		})

		it('validate the user was added on backend', () => {
			cy.runOccCommand(`user:info --output=json '${testUser.userId}'`).then((output) => {
				cy.wrap(output.code).should('eq', 0)
				cy.wrap(JSON.parse(output.stdout)?.groups).should('include', groupName)
			})
		})
	})

	describe('Delete an empty group', () => {
		const groupName = randomString(7)

		before(() => {
			cy.runOccCommand(`group:add '${groupName}'`)
			cy.reload()
		})

		it('see that the group is in the list', () => {
			cy.get('ul.app-navigation__list').within(() => {
				// see that the list of groups contains the group foo
				cy.contains(groupName).should('exist')
				// open the actions menu for the group
				cy.contains('li', groupName).within(() => {
					cy.get('button.action-item__menutoggle').click()
				})
			})
		})

		it('can delete the group', () => {
			// The "Remove group" action in the actions menu is shown and clicked
			cy.get('.action-item__popper button').contains('Remove group').should('exist').click()
			// And confirmation dialog accepted
			cy.get('.modal-container button').contains('Confirm').click()

			// Ignore failure if modal is not shown
			cy.once('fail', (error) => {
				expect(error.name).to.equal('AssertionError')
				expect(error).to.have.property('node', '.modal-container')
			})
			// Make sure no confirmation modal is shown on top of the Remove group modal
			cy.get('body').find('.modal-container').then(($modals) => {
				if ($modals.length > 1) {
					cy.wrap($modals.first()).find('input[type="password"]').type(admin.password)
					cy.wrap($modals.first()).find('button').contains('Confirm').click()
				}
			})
		})

		it('deleted group is not shown anymore', () => {
			cy.get('ul.app-navigation__list').within(() => {
				// see that the list of groups does not contain the group
				cy.contains(groupName).should('not.exist')
			})
			// and also not in database
			cy.runOccCommand(`group:info '${groupName}'`).then((el) => cy.wrap(el.code).should('not.equal', 0))
		})
	})

	describe('Delete a non empty group', () => {
		const groupName = randomString(7)

		before(() => {
			cy.runOccCommand(`group:add '${groupName}'`)
			cy.createRandomUser().then((user) => {
				cy.runOccCommand(`group:addUser '${groupName}' '${user.userId}'`)
			})
			cy.reload()
		})

		it('see that the group is in the list', () => {
			// see that the list of groups contains the group
			cy.get('ul.app-navigation__list').contains('li', groupName).should('exist')
		})

		it('can delete the group', () => {
			// open the menu
			cy.get('ul.app-navigation__list')
				.contains('li', groupName)
				.find('button.action-item__menutoggle')
				.click()

			// The "Remove group" action in the actions menu is shown and clicked
			cy.get('.action-item__popper button').contains('Remove group').should('exist').click()
			// And confirmation dialog accepted
			cy.get('.modal-container button').contains('Confirm').click()

			// Ignore failure if modal is not shown
			cy.once('fail', (error) => {
				expect(error.name).to.equal('AssertionError')
				expect(error).to.have.property('node', '.modal-container')
			})
			// Make sure no confirmation modal is shown on top of the Remove group modal
			cy.get('body').find('.modal-container').then(($modals) => {
				if ($modals.length > 1) {
					cy.wrap($modals.first()).find('input[type="password"]').type(admin.password)
					cy.wrap($modals.first()).find('button').contains('Confirm').click()
				}
			})
		})

		it('deleted group is not shown anymore', () => {
			cy.get('ul.app-navigation__list').within(() => {
				// see that the list of groups does not contain the group foo
				cy.contains(groupName).should('not.exist')
			})
			// and also not in database
			cy.runOccCommand(`group:info '${groupName}'`).then((el) => cy.wrap(el.code).should('not.equal', 0))
		})
	})
})
