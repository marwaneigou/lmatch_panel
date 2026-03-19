<template>
    <div class="searchable-select-wrapper" style="position:relative;">
        <div class="input-group">
            <input
                type="text"
                class="form-control"
                v-model="search"
                :placeholder="currentLabel"
                @focus="isOpen = true"
                @blur="delayClose"
            />
            <div class="input-group-append" @mousedown.prevent="isOpen = !isOpen" style="cursor:pointer;">
                <span class="input-group-text"><i class="fa fa-chevron-down"></i></span>
            </div>
        </div>
        <div
            v-show="isOpen"
            class="list-group shadow"
            style="position:absolute;z-index:9999;width:100%;max-height:220px;overflow-y:auto;border-radius:4px;"
        >
            <button
                type="button"
                class="list-group-item list-group-item-action"
                :class="{ active: !value }"
                @mousedown.prevent="select('', allLabel)"
            >{{ allLabel }}</button>
            <button
                type="button"
                class="list-group-item list-group-item-action"
                v-for="opt in filtered"
                :key="opt.user_id"
                :class="{ active: opt.user_id == value }"
                @mousedown.prevent="select(opt.user_id, opt.user)"
            >{{ opt.user }}</button>
            <div v-if="filtered.length === 0" class="list-group-item text-muted small">No results</div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SearchableSelect',
    props: {
        value: { default: '' },
        options: { type: Array, default: () => [] },
        allLabel: { type: String, default: 'All' },
    },
    data() {
        return {
            search: '',
            isOpen: false,
            currentLabel: this.allLabel,
        };
    },
    computed: {
        filtered() {
            const opts = this.options.filter(o => o.user);
            if (!this.search) return opts;
            const q = this.search.toLowerCase();
            return opts.filter(o => o.user.toLowerCase().includes(q));
        },
    },
    methods: {
        select(id, label) {
            this.currentLabel = label || this.allLabel;
            this.search = '';
            this.isOpen = false;
            this.$emit('input', id);
            this.$emit('change', id);
        },
        delayClose() {
            setTimeout(() => { this.isOpen = false; }, 200);
        },
    },
    watch: {
        value(newVal) {
            if (!newVal) {
                this.currentLabel = this.allLabel;
                this.search = '';
            }
        },
        options() {
            if (this.value) {
                const opt = this.options.find(o => o.user_id == this.value);
                if (opt) this.currentLabel = opt.user;
            }
        },
    },
};
</script>
