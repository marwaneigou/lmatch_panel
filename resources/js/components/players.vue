<style>
.error {
  color: red;
}
.swal2-title {
  font-size: 1.125em !important;
}
</style>

<template>
  <div class="container-fluid">
    <div class="row mb-4 d-flex justify-content-center">
      <div class="col-md-8">
        <div class="card">
          <div class="card-header">Players</div>
          <div class="card-body">
            <div class=" row" style="align-items: center">
              <div class="panel-body col-12 col-md-10 col-lg-10 col-xl-10 mb-4">
                <a href="javascript::void(0)" target="_blank"
                  ><img
                    src="assets/img/siptv.png"
                    title="Smart IPTV"
                    style="width: 126px"
                    alt=""
                /></a>
                <router-link to="/players/arcplayer">
                  <img
                    src="assets/img/arcplayer.png"
                    title="ARC Player"
                    style="width: 126px"
                    alt=""/>
                </router-link>
                <a href="javascript::void(0)" target="_blank"
                  ><img
                    src="assets/img/duplex.png"
                    title="DuplexPlay"
                    style="width: 126px"
                    alt=""
                /></a>
                <a href="javascript::void(0)" target="_blank"
                  ><img
                    src="assets/img/setiptv.png"
                    title="SET IPTV"
                    style="width: 126px"
                    alt=""
                /></a>
                <a href="http://www.wolftv.pro/list" target="_blank"
                  ><img
                    src="assets/img/wolf.png"
                    title="wolf tv pro"
                    style="width: 126px"
                    alt=""
                /></a>
                <a href="javascript::void(0)" target="_blank"
                  ><img
                    src="assets/img/bay-tv.png"
                    title="BAY TV"
                    style="width: 126px"
                    alt=""
                /></a>
                <a href="https://www.netiptv.eu/upload" target="_blank"
                  ><img
                    src="assets/img/net-iptv.png"
                    title="Net IPTV"
                    style="width: 126px"
                    alt=""
                /></a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style>
#m3u_plus_active:focus {
  border-color: white;
}
</style>

<script>
import VueRecaptcha from "vue-recaptcha";
export default {
  components: { VueRecaptcha },
  mounted() {
    Vue.prototype.$userType = document
      .querySelector("meta[name='user-type']")
      .getAttribute("content");
    Vue.prototype.$userSolde = document
      .querySelector("meta[name='user-solde']")
      .getAttribute("content");
  },
  data() {
    return {};
  },

  methods: {},

  created() {},
};
</script>


