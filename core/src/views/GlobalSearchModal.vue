<template>
	<NcModal v-if="isVisible"
		id="global-search"
		:show.sync="isVisible"
		:clear-view-delay="0"
		:title="t('Global search')"
		@close="closeModal">
		<!-- Global search form -->
		<div class="global_search_modal">
			<NcInputField :value.sync="searchQuery" type="text" :label="t('core', 'Global search')" />
			<div class="filters">
				<NcInputField :value.sync="placesFilter" type="text" :label="t('core', 'Places')" />
				<NcDateTimePicker v-model="dateTimeFilter" type="datetime" confirm />
				<NcSelect v-bind="peopleSeclectProps" v-model="peopleSeclectProps.value" />
			</div>
			<div class="filters-applied">
				<FilterChip v-for="filter in filters"
					:key="filter.id"
					:filter="filter"
					@remove-filter="removeFilter">
					<template #icon>
						<AccountIcon v-if="filter.type === 'person'" />
						<img v-else :src="filter.icon" alt="">
					</template>
				</FilterChip>
			</div>
			<div v-for="appResult in dummyAppResults" class="results">
				<div class="result">
					<div class="result-title">
						<span>{{ appResult.appName }}</span>
					</div>
					<div class="result-items">
						<SearcResultItem v-for="result in [{id: 1}, {id: 2}, {id: 3}, {id: 4}]" :key="result.id">
							<template v-if="result.appName === 'Files'" #icon>
								<FolderIcon :size="40" />
							</template>
							<template v-else #icon>
								<AccountIcon :size="40" />
							</template>
						</SearcResultItem>
					</div>
					<div class="result-footer">
						<NcButton type="tertiary-no-background">
							Load more results
							<template #icon>
								<DotsHorizontalIcon :size="20" />
							</template>
						</NcButton>
						<NcButton alignment="end-reverse" type="tertiary-no-background">
							Search in {{ appResult.appName.toLowerCase() }}
							<template #icon>
								<ArrowRight :size="20" />
							</template>
						</NcButton>
					</div>
				</div>
			</div>
		</div>
	</NcModal>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'
import NcDateTimePicker from '@nextcloud/vue/dist/Components/NcDateTimePicker.js'
import NcInputField from '@nextcloud/vue/dist/Components/NcInputField.js'
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js'
import DotsHorizontalIcon from 'vue-material-design-icons/DotsHorizontal.vue'
import ArrowRight from 'vue-material-design-icons/ArrowRight.vue'
import FolderIcon from 'vue-material-design-icons/Folder.vue'
import AccountIcon from 'vue-material-design-icons/AccountCircle.vue'

import SearcResultItem from '../components/GlobalSearch/SearchResultItem.vue'
import FilterChip from '../components/GlobalSearch/SearchFilterChip.vue'

export default {
	name: 'GlobalSearchModal',
	components: {
		NcButton,
		NcModal,
		NcSelect,
		NcInputField,
		ArrowRight,
		DotsHorizontalIcon,
		SearcResultItem,
		NcDateTimePicker,
		FolderIcon,
		AccountIcon,
		FilterChip,
	},
	props: {
		isVisible: {
			type: Boolean,
			required: true,
		},
	},
	data() {
		return {
			searchQuery: '',
			placesFilter: '',
			dateTimeFilter: null,
			filters: [
				{ id: 1, text: 'Files', type: 'app', icon: '/apps/files/img/app.svg' },
				{ id: 2, text: 'Talk', type: 'app', icon: '/apps/spreed/img/app.svg' },
				{ id: 3, text: 'Mail', type: 'app', icon: '/apps/files/img/app.svg' },
				{ id: 4, text: '12th June 2023', type: 'date', pretext: 'Modified before' },
				{ id: 5, text: 'Maniaka Lambo', type: 'person', icon: '/apps/files/img/app.svg' },
			],
			dummyAppResults: [
				{ appName: 'Files' },
				{ appName: 'Talk' },
			],
		}
	},

	computed: {
		peopleSeclectProps: {
			get() {
				return {
					// inputId: getRandomId(),
					multiple: true,
					placement: 'top',
					options: this.getUsers(),
				}
			},

		},

	},
	methods: {
		closeModal() {
			this.isVisible = false
		},
		removeFilter(filter) {
			for (let i = 0; i < this.filters.length; i++) {
				if (this.filters[i].id === filter.id) {
					this.filters.splice(i, 1)
					break
				}
			}
		},
		userTopApps() {
			return [
				{ appName: 'Files', icon: '/apps/files/img/app.svg' },
				{ appName: 'Talk', icon: '/apps/spreed/img/app.svg' },
				{ appName: 'Mail', icon: '/apps/spreed/img/app.svg' },
			]
		},
		getUsers() {
			return [
				'foo',
				'bar',
				'baz',
				'qux',
				'quux',
			]
		},
	},
}
</script>

<style lang="scss" scoped>
.global_search_modal {
	padding: 10px 20px 10px 20px;

	& .filters {
		display: flex;
		padding-top: 5px;
		align-items: center;
		justify-content: space-between;

		::v-deep>* {
			min-width: auto;
			/* Reset hard set min widths */
			min-height: 0;
			/* Reset any min heights */
			display: flex;
			align-items: center;
			flex: 1;

			>* {
				flex: 1;
				min-width: auto;
				/* Reset hard set min widths */
				min-height: 0;
			}
		}

		::v-deep>*:not(:last-child) {
			margin: 0 2px;
		}
	}

	& .filters-applied {
		display: flex;
		flex-wrap: wrap;
	}

	& .results {
		padding: 10px;

		& .result {

			& .result-title {
				span {
					color: var(--color-primary-element);
					font-weight: bolder;
					font-size: 16px;
				}
			}

			& .result-footer {
				justify-content: space-between;
				align-items: center;
				display: flex;
			}
		}

	}
}
</style>
