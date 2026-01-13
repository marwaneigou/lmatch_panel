<template>
  <div class="container-fluid">
    	<div class="row">
        <div class="col-md-4">
          <div class="dbox dbox--color-1">
            <div class="dbox__icon">
              <i class="fa fa-table" aria-hidden="true"></i>
            </div>
            <div class="dbox__body">
              <span class="dbox__count">{{ counts.ActiveCode }}</span>
              <span class="dbox__title">{{trans('acCode_total')}}</span>
            </div>            
            <div class="dbox__action">
              <router-link to="/ActiveCode" class="dbox__action__btn">
                {{trans('more')}}
              </router-link>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="dbox dbox--color-2">
            <div class="dbox__icon">
              <i class="fa fa-users" aria-hidden="true"></i>
            </div>
            <div class="dbox__body">
              <span class="dbox__count">{{ counts.Users }}</span>
              <span class="dbox__title">{{trans('users_total')}}</span>
            </div>            
            <div class="dbox__action">
              <router-link to="/Users" class="dbox__action__btn">
                {{trans('more')}}
              </router-link>
            </div>				
          </div>
        </div>
        <div class="col-md-4">
          <div class="dbox dbox--color-3">
            <div class="dbox__icon">
              <i class="fa fa-mobile" style="font-size: 22px;" aria-hidden="true"></i>
            </div>
            <div class="dbox__body">
              <span class="dbox__count">{{ counts.MagDevice }}</span>
              <span class="dbox__title">{{trans('mgDevice_total')}}</span>
            </div>
            
            <div class="dbox__action">
              <router-link to="/MagDevice" class="dbox__action__btn">
                {{trans('more')}}
              </router-link>
            </div>				
          </div>
        </div>
        <div class="col-md-4">
          <div class="dbox dbox--color-2">
            <div class="dbox__icon">
              <i class="fa fa-th-list" aria-hidden="true"></i>
            </div>
            <div class="dbox__body">
              <span class="dbox__count">{{ counts.Category }}</span>
              <span class="dbox__title">{{trans('categories')}}</span>
            </div>            
            <div class="dbox__action">
              <router-link to="/Category" class="dbox__action__btn">
                {{trans('more')}}
              </router-link>
            </div>				
          </div>
        </div>
        <div class="col-md-4">
          <div class="dbox dbox--color-3">
            <div class="dbox__icon">
              <i class="fa fa-television" aria-hidden="true"></i>
            </div>
            <div class="dbox__body">
              <span class="dbox__count">{{ counts.Channel }}</span>
              <span class="dbox__title">{{trans('channels')}}</span>
            </div>
            
            <div class="dbox__action">
              <router-link to="/Channel" class="dbox__action__btn">
                {{trans('more')}}
              </router-link>
            </div>				
          </div>
        </div>
        <div class="col-md-4">
          <div class="dbox dbox--color-1">
            <div class="dbox__icon">
              <i class="fa fa-users" aria-hidden="true"></i>
            </div>
            <div class="dbox__body">
              <span class="dbox__count">{{ counts.Resiler }}</span>
              <span class="dbox__title">{{trans('resellers')}}</span>
            </div>            
            <div class="dbox__action">
              <router-link to="/Resilers" class="dbox__action__btn">
                {{trans('more')}}
              </router-link>
            </div>
          </div>
        </div>
      </div>
      
      <div class="row justify-content-center">
  <!-- NAX Player alone, centered -->
  <div class="col-12 text-center mb-4">
    <a href="https://naxplayer.com/device/dashboard" target="_blank">
      <img src="assets/img/nax.png" title="NAX Player" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
  </div>

  <!-- Other players -->
  <div class="d-flex flex-wrap justify-content-center gap-3">
    <a href="https://iboiptv.com/" target="_blank">
      <img src="assets/img/ibo2_2.png" title="IBO IPTV" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://iboplayer.pro/manage-playlists/login" target="_blank">
      <img src="assets/img/ibopro.jpg" title="IBO PRO APP" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://www.arcplayer.com/manage" target="_blank">
      <img src="assets/img/arc_2.png" title="ARC Player" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://siptv.eu/mylist/" target="_blank">
      <img src="assets/img/smart_2.png" title="Smart IPTV" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://setsysteme.com/set.app" target="_blank">
      <img src="assets/img/set_2.png" title="SET IPTV" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="http://www.wolftv.pro/list" target="_blank">
      <img src="assets/img/wolf_2.png" title="Wolf TV Pro" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://cms.bayip.tv" target="_blank">
      <img src="assets/img/bay_2.png" title="BAY TV" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://manage.roomiptv.com/multi" target="_blank">
      <img src="assets/img/room_2.png" title="Room IPTV" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://www.netiptv.eu/upload" target="_blank">
      <img src="assets/img/net_2.png" title="Net IPTV" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://masaplayer.com/mylist" target="_blank">
      <img src="assets/img/masa_2.png" title="MASAPLAYER" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
    <a href="https://virginia-player.com/" target="_blank">
      <img src="assets/img/virginia_2.png" title="Virginia Player" class="img-fluid" style="max-width:126px;" alt=""/>
    </a>
  </div>
</div>



    
    <div class="row mb-4" v-show="counts.settings.ads != ''">
      <div class="col-12">
        <h1 class="m-0 text-dark">{{trans('ads')}}</h1>
      </div>
      <div class="col-12 d-flex justify-content-center mt-4">
        <img style="height: 300px;" :src="getImgUrl(counts.settings.ads)">
      </div>
    </div>
    
    <div class="row my-5" v-if="this.$showMessage == 1">
      <div class="col-12">
        <h1 class="m-0 text-dark">{{trans('down_soft')}}</h1>
      </div>
      <div>
        <span style="color: rgb(91, 91, 174); height: 250px">
          <a
            v-if="counts.change_url == 'hide'"
            href="http://soft.newtvhd.com"
            target="_blank"
            ><img
              src="assets/img/téléchargement.jpg"
              title="Télécharger les softwares"
              style="max-width: 450px; max-height: 435px; width:100%;"
              alt=""
          /></a>
          <a
            v-if="counts.change_url == 'show'"
            href="http://soft.class.newtvhd.com"
            target="_blank"
            ><img
              src="assets/img/téléchargement.jpg"
              title="Télécharger les softwares"
              style="max-width: 450px; max-height: 435px; width:100%;"
              alt=""
          /></a>
        </span>
      </div>
    </div>

    <div class="roww my-5" style="align-items: center;" v-if="this.user_type == 'Admin'">
      <div class="col-12">
        <h1 class="m-0 text-dark">{{trans('users_solde_2')}}</h1>
      </div>
      <table class="table table-hover">
        <thead>
          <tr>
            <th>#</th>
            <th>{{trans('username')}}</th>
            <th>{{trans('solde')}}</th>
          </tr>
        </thead>
        <tr>
          <td colspan="10" class="text-center" v-if="onLoad">
            <div class="spinner-border" role="status">
              <span class="sr-only">{{trans('loading')}}</span>
            </div>
          </td>
        </tr>
        <tr v-for="user in users_list.data" :key="user.id">
            <td>{{user.id}}</td>
            <td>{{user.name}}</td>
            <td>{{user.solde}}</td>
        </tr>
      </table>
      <div>
        <vue-paginate-al
          :totalPage="this.users_list.last_page"
          activeBGColor="success"
          @btnClick="checkSolde"
          :withNextPrev="false"
        ></vue-paginate-al>
      </div>
    </div>
  </div>
</template>

<style>

.dbox {
    position: relative;
    background: rgb(255, 86, 65);
    background: -moz-linear-gradient(top, rgba(255, 86, 65, 1) 0%, rgba(253, 50, 97, 1) 100%);
    background: -webkit-linear-gradient(top, rgba(255, 86, 65, 1) 0%, rgba(253, 50, 97, 1) 100%);
    background: linear-gradient(to bottom, rgba(255, 86, 65, 1) 0%, rgba(253, 50, 97, 1) 100%);
    filter: progid: DXImageTransform.Microsoft.gradient( startColorstr='#ff5641', endColorstr='#fd3261', GradientType=0);
    border-radius: 4px;
    text-align: center;
    margin: 60px 0 50px;
}
.dbox__icon {
    position: absolute;
    transform: translateY(-50%) translateX(-50%);
    left: 50%;
}
.dbox__icon:before {
    width: 75px;
    height: 75px;
    position: absolute;
    background: #fda299;
    background: rgba(253, 162, 153, 0.34);
    content: '';
    border-radius: 50%;
    left: -17px;
    top: -17px;
    z-index: -2;
}
.dbox__icon:after {
    width: 60px;
    height: 60px;
    position: absolute;
    background: #f79489;
    background: rgba(247, 148, 137, 0.91);
    content: '';
    border-radius: 50%;
    left: -10px;
    top: -10px;
    z-index: -1;
}
.dbox__icon > i {
    background: #ff5444;
    border-radius: 50%;
    line-height: 40px;
    color: #FFF;
    width: 40px;
    height: 40px;
	font-size:22px;
}
.dbox__body {
    padding: 50px 20px;
}
.dbox__count {
    display: block;
    font-size: 30px;
    color: #FFF;
    font-weight: 300;
}
.dbox__title {
    font-size: 13px;
    color: #FFF;
    color: rgba(255, 255, 255, 0.81);
}
.dbox__action {
    transform: translateY(-50%) translateX(-50%);
    position: absolute;
    left: 50%;
}
.dbox__action__btn {
    border: none;
    background: #FFF;
    border-radius: 19px;
    padding: 7px 16px;
    text-transform: uppercase;
    font-weight: 500;
    font-size: 11px;
    letter-spacing: .5px;
    color: #003e85;
    box-shadow: 0 3px 5px #d4d4d4;
}


.dbox--color-2 {
    background: rgb(252, 190, 27);
    background: -moz-linear-gradient(top, rgba(252, 190, 27, 1) 1%, rgba(248, 86, 72, 1) 99%);
    background: -webkit-linear-gradient(top, rgba(252, 190, 27, 1) 1%, rgba(248, 86, 72, 1) 99%);
    background: linear-gradient(to bottom, rgba(252, 190, 27, 1) 1%, rgba(248, 86, 72, 1) 99%);
    filter: progid: DXImageTransform.Microsoft.gradient( startColorstr='#fcbe1b', endColorstr='#f85648', GradientType=0);
}
.dbox--color-2 .dbox__icon:after {
    background: #fee036;
    background: rgba(254, 224, 54, 0.81);
}
.dbox--color-2 .dbox__icon:before {
    background: #fee036;
    background: rgba(254, 224, 54, 0.64);
}
.dbox--color-2 .dbox__icon > i {
    background: #fb9f28;
}

.dbox--color-3 {
    background: rgb(183,71,247);
    background: -moz-linear-gradient(top, rgba(183,71,247,1) 0%, rgba(108,83,220,1) 100%);
    background: -webkit-linear-gradient(top, rgba(183,71,247,1) 0%,rgba(108,83,220,1) 100%);
    background: linear-gradient(to bottom, rgba(183,71,247,1) 0%,rgba(108,83,220,1) 100%);
    filter: progid:DXImageTransform.Microsoft.gradient( startColorstr='#b747f7', endColorstr='#6c53dc',GradientType=0 );
}
.dbox--color-3 .dbox__icon:after {
    background: #b446f5;
    background: rgba(180, 70, 245, 0.76);
}
.dbox--color-3 .dbox__icon:before {
    background: #e284ff;
    background: rgba(226, 132, 255, 0.66);
}
.dbox--color-3 .dbox__icon > i {
    background: #8150e4;
}
</style>


<script>
export default {
  mounted() {
    this.getUserAuth();
    this.getCount();   
    
  },
  data() {
    return {
      user_type: "",
      user_solde: "",
      counts: {},
      users_list:[],
      onLoad:false
    };
  },
  methods: {
    getUserAuth() {
      axios.post("code/GetUserAuth").then((response) => {
        this.user_type = response.data.user_type;
        this.user_solde = response.data.user_solde;
        if(this.user_type == 'Admin') {
          this.checkSolde();
        }
      });
    },

    getCount() {
      axios.post("code/GetCount").then(({ data }) => (this.counts = data));
    },

    checkSolde(page = 1) {
      this.onLoad = true;
      axios.post("code/checkSolde?page="+page).then(({ data }) => (this.users_list = data, this.onLoad = false));
    },

    getImgUrl(img) {
      var images = "/assets/img/" + img;
      return images;
    },

    
  },

  created() {
    this.getUserAuth();
    this.getCount();
  }
};
</script>
