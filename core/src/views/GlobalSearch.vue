 <!--
  - @copyright Copyright (c) 2020 Fon E. Noel NFEBE <fenn25.fn@gmail.com>
  -
  - @author Fon E. Noel NFEBE <fenn25.fn@gmail.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program.  If not, see <http://www.gnu.org/licenses/>.
  -
  -->
<template>
    <div>
        <NcButton aria-label="Global search" @click="toggleGlobalSearch">
            <template #icon>
                <Magnify class="unified-search__trigger" :size="22" />
            </template>
        </NcButton>
        <GlobalSearchModal :isVisible="showGlobalSearch" />
    </div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import Magnify from 'vue-material-design-icons/Magnify.vue'
import GlobalSearchModal from './GlobalSearchModal.vue';

export default {
    name: 'GlobalSearch',
    components: {
        NcButton,
        Magnify,
        GlobalSearchModal,
    },
    data() {
        return {
            showGlobalSearch: false,
        };
    },
    mounted() {
        console.debug("Global search initialized!")
    },
    methods: {
        toggleGlobalSearch() {
            this.showGlobalSearch = !this.showGlobalSearch
        }
    }
};
</script>
  
<style lang="scss" scoped></style>
