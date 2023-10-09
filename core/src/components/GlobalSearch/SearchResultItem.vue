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
  <div class="result-item">
    <slot name="icon"></slot>
    <div class="details">
      <span>Result Title</span>
      <small>More details about result</small>
    </div>
  </div>
</template>

<script>

export default {
  name: 'SearchResultItem',
  components: {
  },

  data() {
    return {
    }
  },

  methods: {

  },
}
</script>

<style lang="scss" scoped>
.result-item {
  display: flex;
  padding: 5px; /* Add padding for the border effect */
  border: 1px solid #f0f0f0; /* Faint border */
  border-radius: 10px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12); /* Light shadow */
  transition: background-color 0.3s ease; /* Smooth hover transition */

  & .details {
    display: flex;
    flex-direction: column;
  }

  &:hover {
    background-color: var(--color-placeholder-light);
  }
}
</style>
