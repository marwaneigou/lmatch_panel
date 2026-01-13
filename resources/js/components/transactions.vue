<template>
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <div class="row">
              <div class="col-lg-7 col-md-4 col-sm-4 col-xs-4">
                <h5>{{trans('trans')}}</h5>
              </div>
              <div class="col-lg-3 col-md-6 col-sm-12 col-xs-4 mb-2">
                <select class="form-control" v-model="byUser" @change="showResDet()">
                  <option value="">{{trans('all')}}</option>
                  <option v-if="res.user" v-for="res in resllers" v-bind:key="res.user_id" :value="res.user_id">{{res.user ? res.user : ''}}</option>
                </select>
              </div>
            </div>
          </div>
          <div class="card-body table-responsive p-0">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>{{trans('user')}}</th>
                  <th>{{trans('operation')}}</th>
                  <th>{{trans('desc')}}</th>
                  <th>{{trans('credits')}} / {{trans('test_cred')}}</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tr>
                <td colspan="10" class="text-center" v-if="onLoad">
                  <div class="spinner-border" role="status">
                    <span class="sr-only">{{trans('loading')}}</span>
                  </div>
                </td>
              </tr>
              <tr v-for="item in items.data" :key="item.id">
                <td>{{ item.reseller }}</td>
                <td v-if="item.operation == 0"><i class="text-danger fa fa-minus-square" style="font-size:20px;"></i></td>
                <td v-if="item.operation == 1"><i class="text-success fa fa-plus-square" style="font-size:20px;"></i></td>
                <td v-if="item.operation == 2"><i class="text-info fa fa-info" style="font-size:20px;"></i></td>
                <td>{{ item.description }}</td>
                <td v-if="item.operation != 2">{{ item.solde }}</td>
                <td v-else></td>
                <td>{{ item.date }}</td>
              </tr>
            </table>
          </div>

          <div class="card-footer">
            <vue-paginate-al
              :totalPage="this.items.last_page"
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
export default {
  mounted() {
    Vue.prototype.$userType = document
      .querySelector("meta[name='user-type']")
      .getAttribute("content");
    Vue.prototype.$userSolde = document
      .querySelector("meta[name='user-solde']")
      .getAttribute("content");

    this.getpage();
    this.all_resellers()
  },
  data() {
    return {
      items: { id: "" },
      onLoad: true,
      resllers: [],
      byUser:'',
    };
  },
  methods: {
    getpage(page = 1) {
      this.onload = true;
      if(this.byUser == '') {
        axios.post("code/transactions_all?page=" + page).then((response) => {
          this.items = response.data;
          this.onLoad = false;
        });
      }else{
        axios.post("code/transactions_by_res?page=" + page+"&res_id="+this.byUser).then((response) => {
          this.items = response.data;
          this.onLoad = false;
        });
      }
    },
    all_resellers() {
      axios.post("code/all_resellers").then(({ data }) => (this.resllers = data));
    },
    showResDet() {
      this.searchQuery = '';
      this.getpage();
    },
  }
};
</script>