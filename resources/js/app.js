/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

window.Vue = require('vue');

window.VueRouter = require('vue-router').default;
// import store from './store'

import VueProgressBar from 'vue-progressbar';
import StarRating from 'vue-dynamic-star-rating'

Vue.use(VueProgressBar, {
  color: 'rgb(143, 255, 199)',
  failedColor: 'red',
  height: '2px'
})

Vue.use(VueRouter)

Vue.component('active-component', require('./components/active.vue').default)
Vue.component('active02-component', require('./components/active02.vue').default)
Vue.component('star-rating', StarRating);

Vue.prototype.$userType = document.querySelector("meta[name='user-type']").getAttribute('content');
Vue.prototype.$userSolde = document.querySelector("meta[name='user-solde']").getAttribute('content');
Vue.prototype.$userSoldeTest = document.querySelector("meta[name='user-solde-test']").getAttribute('content');
Vue.prototype.$userSoldeApp = document.querySelector("meta[name='user-solde-app']").getAttribute('content');
Vue.prototype.$userGift = document.querySelector("meta[name='user-gift']").getAttribute('content');
Vue.prototype.$showMessage = document.querySelector("meta[name='show-message']").getAttribute('content');
Vue.prototype.$lang = document.querySelector('meta[name="lang"]').content;




Vue.mixin({
  methods: {
    trans(key) {
      let lang = require("../lang/" + this.$lang + '/lang' + ".json");
      return lang[key];
    }
  },
})

const routes = [
    {
      name:'profile', 
      path: '/profile', 
      component: require('./components/Profile.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},
     
    
    {
      name:'dashboard', 
      path: '/', 
      component: require('./components/dashboard.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}, {name:"showMessage", content:Vue.prototype.$showMessage}]},


    {
      name:'ActiveCode', 
      path: '/ActiveCode', 
      component: require('./components/ActiveCode.vue').default , 
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},

    {
      name:'Users', 
      path: '/Users', 
      component: require('./components/Users.vue').default , 
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},

    {
      name:'Category', 
      path: '/Category', 
      component: require('./components/Category.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}],
      beforeEnter: (to, from, next) => {
        if(Vue.prototype.$userType != 'Admin') {
          next({ name: 'dashboard' });
        }else{
          next();
        }
      }
    },

    {
      name:'Resilers', 
      path: '/Resilers', 
      component: require('./components/Resilers.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},

    {
      name:'MasterCode', 
      path: '/MasterCode', 
      component: require('./components/MasterCode.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}],
      beforeEnter: (to, from, next) => {
        if(Vue.prototype.$userType != 'Admin') {
          next({ name: 'dashboard' });
        }else{
          next();
        }
      }
    },

      {
        name:'MagDevice', 
        path: '/MagDevice', 
        component: require('./components/MagDevice.vue').default,
        meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},

    {
      name:'MassCode', 
      path: '/MassCode', 
      component: require('./components/MultiCode.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},

    {
      name:'Inbox', 
      path: '/Inbox', 
      component: require('./components/Inbox.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},

    {
      name:'Transactions', 
      path: '/Transactions', 
      component: require('./components/Transactions.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},

    {
      name:'Channel', 
      path: '/Channel', component: require('./components/Channel.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}],
      beforeEnter: (to, from, next) => {
        if(Vue.prototype.$userType != 'Admin') {
          next({ name: 'dashboard' });
        }else{
          next();
        }
      }
    },
    {
      name:'active', 
      path: '/active', 
      component: require('./components/active.vue').default,
      meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},
      {
        name:'active02', 
        path: '/active02', 
        component: require('./components/active02.vue').default,
        meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]
      },
      {
        name:'arcplayer', 
        path: '/players/arcplayer', 
        component: require('./components/arcplayer.vue').default,
        meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}],
        beforeEnter: (to, from, next) => {
          if(Vue.prototype.$userSoldeApp == '0' && Vue.prototype.$userType != 'Admin') {
            next({ name: 'applications' });
          }else{
            next();
          }
        }
      },
       
      {
        name:'players', 
        path: '/players', 
        component: require('./components/players.vue').default,
        meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},      
      {
        name:'speedtest', 
        path: '/speedtest', 
        component: require('./components/speedtest.vue').default,
        meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}]},
      {
        name:'hosts', 
        path: '/hosts', 
        component: require('./components/hosts.vue').default,
        meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}],
        beforeEnter: (to, from, next) => {
          if(Vue.prototype.$userType != 'Admin') {
            next({ name: 'dashboard' });
          }else{
            next();
          }
        }
      },
      {
        name:'applications', 
        path: '/applications', 
        component: require('./components/applications.vue').default,
        meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}],
      },
      {
        name:'arcplayer', 
        path: '/arcplayer', 
        component: require('./components/app_arcplayer.vue').default,
        meta : [{name:"usersolde", content:Vue.prototype.$userSolde}, {name:"userSoldeTest", content:Vue.prototype.$userSoldeTest}, {name:"userSoldeApp", content:Vue.prototype.$userSoldeApp}, {name:"userType", content:Vue.prototype.$userType}, {name:"userGift", content:Vue.prototype.$userGift}, {name:"lang", content:Vue.prototype.$lang}],
      },
  ];


  


  // router.beforeEach((to, from, next) => {
  //   if (!Admin) next('/')
  //   else next()
  // })

  import VuePaginateAl from 'vue-paginate-al'
  Vue.component('vue-paginate-al', VuePaginateAl)

  

  
  const router = new VueRouter({ mode: 'history', routes: routes });

  import Swal from 'sweetalert2'

  window.Swal = Swal;


  
  const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    onOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer)
      toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
  })
  
    window.toast = toast;
 
window.Fire = new Vue();

// Vue.component('my-vuetable', require('./components/MyVuetable.vue'));


Vue.component(
  'pagination', 
  require('laravel-vue-pagination')
  );

  Vue.component('paginate', VuejsPaginate)

/**
 * The following block of code may be used to automatically register your
 * Vue components. It will recursively scan this directory for the Vue
 * components and automatically register them with their "basename".
 *
 * Eg. ./components/ExampleComponent.vue -> <example-component></example-component>
 */

// const files = require.context('./', true, /\.vue$/i);
// files.keys().map(key => Vue.component(key.split('/').pop().split('.')[0], files(key).default));



/**
 * Next, we will create a fresh Vue application instance and attach it to
 * the page. Then, you may begin adding components to this application
 * or customize the JavaScript scaffolding to fit your unique needs.
 */

const app = new Vue({
    el: '#app',
    router,
});

