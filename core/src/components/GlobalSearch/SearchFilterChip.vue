<template>
	<div class="chip">
		<span class="icon">
			<slot v-if="filter.type === 'app' || filter.type === 'person'" name="icon" />
			<span v-if="filter.type == 'date'"> {{ filter.pretext }} : </span>
		</span>
		<span class="text">{{ filter.text }}</span>
		<span class="close-icon" @click="removeChip">
			<CloseIcon :size="20" />
		</span>
	</div>
</template>

<script>
import CloseIcon from 'vue-material-design-icons/CloseThick.vue'

export default {
	name: 'SearchFilterChip',
	components: {
		CloseIcon,
	},
	props: {
		filter: Object,
	},
	methods: {
		removeChip() {
			this.$emit('remove-filter', this.filter)
		},
	},
}
</script>

<style lang="scss" scoped>
.chip {
    display: flex;
    align-items: center;
    padding: 2px 4px;
    border: 1px solid var(--color-primary-element);
    border-radius: 12px;
    color: var(--color-main-background);
    background-color: var(--color-primary-element);
    margin: 2px;
    font-size: 12px;

    & .icon {
        display: flex;
        align-items: center;
        height: 20px;
        padding-right: 5px;
    }

    & .text {
        margin: 0 2px;
    }

    & .close-icon {
        cursor: pointer;

        &:hover {
            border: 1px solid var(--color-main-background);
            border-radius: 4px;
            box-shadow: 2px 2px 4px rgba(255, 255, 255, 0.5);
            padding: 1px;
        }
    }
}
</style>
