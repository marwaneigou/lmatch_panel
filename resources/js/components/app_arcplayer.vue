<template>
  <div class="container-fluid">
    <div class="row mb-4 justify-content-md-center">
      <div class="col-md-12">
        <div class="card">
          <div class="card-header">
            <div style="display:flex;align-items: baseline;">
              <a href="https://www.arcplayer.com/activation" target="_blank"><img src="assets/img/arc.png" style="height:100px;" alt="ARCPlayer"></a>            
              <div class="ml-4" style="font-size:18px;" v-html="trans('arc_description')"></div>
            </div>            
            <a href="https://www.arcplayer.com/activation" target="_blank"><img src="assets/img/coupon arc.png" style="width: 60%;margin-left: 20%;margin-top: 50px;margin-bottom: 20px;" alt="ARCPlayer"></a>
          </div>
          <div class="card-body table-responsive p-0">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>{{ trans("code_coupon") }}</th>
                  <th>{{ trans("activated_by") }}</th>
                  <th>{{ trans("status") }}</th>
                  <th>{{ trans("action") }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="msg in list.data">
                  <td :id="msg.code">{{ msg.code }}</td>
                  <td v-if="msg.client == null">{{ trans("not_activ") }}</td>
                  <td v-else>{{ msg.client.mac }}</td>
                  <td v-if="msg.expired == 1">{{ trans("expired") }}</td>
                  <td v-else>{{ trans("available") }}</td>
                  <td>
                    <a href="#" @click="copyCode(msg.code)" :title="trans('copy')">
                      <i class="fa fa-copy blue-color"></i>
                    </a>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="card-footer">
            <pagination :data="list" @pagination-change-page="getResults">
              <span slot="prev-nav">&lt; Previous</span>
              <span slot="next-nav">Next &gt;</span>
            </pagination>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { VueEditor } from "vue2-editor";

export default {
  mounted() {
    this.getList();
  },
  data() {
    return {
      list: {
        id: "",
        user_id: "",
        code: "",
        type: "",
      },
    };
  },
  methods: {
    getList() {
      axios.post("code/coupons").then(({ data }) => (this.list = data));
    },
    getResults(page = 1) {
        axios.post('code/coupons?page=' + page)
            .then(response => {
                this.list = response.data;
            });
    },
    copyCode(code) {
      const el = document.createElement('textarea');
      el.value = code;
      document.body.appendChild(el);
      el.select();
      document.execCommand('copy');
      document.body.removeChild(el);
      window.alert('Copied to clipboard!');
    }
  },
};
</script>
