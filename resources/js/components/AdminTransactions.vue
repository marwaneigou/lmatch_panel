<template>
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <div class="row">
              <div class="col-md-3">
                <h5>Transaction Logs</h5>
              </div>
              <div class="col-md-3">
                <div class="dropdown" :class="{ show: showResellerDropdown }">
                    <button class="btn btn-light dropdown-toggle form-control text-left" type="button" @click="toggleResellerDropdown">
                        {{ selectedResellerName }}
                    </button>
                    <div class="dropdown-menu w-100 p-2" :class="{ show: showResellerDropdown }">
                        <input type="text" class="form-control mb-2" v-model="resellerSearch" placeholder="Search reseller..." ref="resellerSearchInput">
                        <div style="max-height: 250px; overflow-y: auto;">
                             <a class="dropdown-item" href="#" @click.prevent="selectReseller('')">All Resellers</a>
                             <a class="dropdown-item" href="#" v-for="res in filteredResellers" :key="res.user_id" @click.prevent="selectReseller(res)">
                                 {{ res.user }}
                             </a>
                        </div>
                    </div>
                </div>
              </div>
              <div class="col-md-3">
                <select class="form-control" v-model="filters.action" @change="getpage()">
                  <option value="">All Actions</option>
                  <option v-for="act in actions" :key="act" :value="act">{{ act }}</option>
                </select>
              </div>
              <div class="col-md-3">
                <input type="text" class="form-control" placeholder="Search..." v-model="filters.search" @keyup.enter="getpage()">
              </div>
            </div>
          </div>
          <div class="card-body table-responsive p-0" @click="showResellerDropdown = false">
            <table class="table table-hover text-nowrap">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Reseller</th>
                  <th>Action</th>
                  <th>Target Type</th>
                  <th>Target ID</th>
                  <th>Details</th>
                  <th>Amount</th>
                  <th>Balance (Old -> New)</th>
                  <th>IP</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="loading">
                  <td colspan="10" class="text-center">
                    <div class="spinner-border" role="status">
                      <span class="sr-only">Loading...</span>
                    </div>
                  </td>
                </tr>
                <tr v-else v-for="item in items.data" :key="item.id">
                  <td>{{ item.id }}</td>
                  <td>{{ item.reseller ? item.reseller.name : item.reseller_id }}</td>
                  <td><span class="badge" :class="getActionClass(item.action)">{{ item.action }}</span></td>
                  <td>{{ item.target_type }}</td>
                  <td>{{ item.target_id }}</td>
                  <td>{{ item.details }}</td>
                  <td>{{ item.amount }}</td>
                  <td>
                    <span v-if="item.old_balance !== null">
                        {{ item.old_balance }} <i class="fa fa-arrow-right"></i> {{ item.new_balance }}
                    </span>
                  </td>
                  <td>{{ item.ip }}</td>
                  <td>{{ item.created_at | formatDate }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <div class="card-footer">
            <vue-paginate-al
              :totalPage="items ? items.last_page : 1"
              activeBGColor="success"
              @btnClick="getpage"
              :withNextPrev="false"
            ></vue-paginate-al>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment';

export default {
  data() {
    return {
      items: {},
      loading: false,
      resellers: [],
      showResellerDropdown: false,
      resellerSearch: '',
      filters: {
        reseller_id: '',
        action: '',
        search: ''
      },
      actions: [
        'create', 'update', 'delete', 'enable', 'disable', 'renew', 
        'reset_mac', 'add_credit', 'recover_credit', 'block', 'unblock', 
        'transfer', 'change_days', 'vpn'
      ]
    };
  },
  filters: {
      formatDate(value) {
          if (value) {
              return moment(String(value)).format('YYYY-MM-DD HH:mm:ss');
          }
      }
  },
  computed: {
      filteredResellers() {
          if (!this.resellerSearch) {
              return this.resellers;
          }
          const search = this.resellerSearch.toLowerCase();
          return this.resellers.filter(res => 
              res.user.toLowerCase().includes(search)
          );
      },
      selectedResellerName() {
          if (!this.filters.reseller_id) {
              return 'All Resellers';
          }
          const selected = this.resellers.find(res => res.user_id == this.filters.reseller_id);
          return selected ? selected.user : 'Unknown Reseller';
      }
  },
  mounted() {
    this.getpage();
    this.fetchResellers();
    document.addEventListener('click', this.closeDropdownOutside);
  },
  beforeDestroy() {
        document.removeEventListener('click', this.closeDropdownOutside);
  },
  methods: {
    getpage(page = 1) {
      this.loading = true;
      axios.post("code/transaction_logs?page=" + page, this.filters)
        .then((response) => {
          this.items = response.data;
          this.loading = false;
        })
        .catch(error => {
            this.loading = false;
            console.error(error);
        });
    },
    fetchResellers() {
      axios.post("code/all_resellers").then(({ data }) => (this.resellers = data));
    },
    toggleResellerDropdown() {
        this.showResellerDropdown = !this.showResellerDropdown;
        if (this.showResellerDropdown) {
             this.$nextTick(() => {
                this.$refs.resellerSearchInput.focus();
            });
        }
    },
    selectReseller(res) {
        this.filters.reseller_id = res ? res.user_id : '';
        this.showResellerDropdown = false;
        this.getpage();
    },
    closeDropdownOutside(e) {
        if (!this.$el.contains(e.target)) {
            this.showResellerDropdown = false;
        }
    },
    getActionClass(action) {
        switch(action) {
            case 'create': return 'badge-success';
            case 'delete': return 'badge-danger';
            case 'disable': return 'badge-danger';
            case 'enable': return 'badge-success';
            case 'update': return 'badge-info';
            case 'renew': return 'badge-primary';
            case 'add_credit': return 'badge-success';
            case 'recover_credit': return 'badge-warning';
            default: return 'badge-secondary';
        }
    }
  }
};
</script>
