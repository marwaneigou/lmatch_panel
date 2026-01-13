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
          <div class="card-header">ARCPlayer</div>
          <div class="card-body">
            <form @submit.prevent="activate()">
              <div class="form-group row">
                <label for="pack" class="col-3 col-form-label">Pack:</label>
                <div class="col-7">
                  <select
                    name="pack"
                    id="pack"
                    class="form-control"
                    v-model="pack"
                  >
                    <option value="1 year">1 year</option>
                    <option value="lifetime">Lifetime</option>
                  </select>
                  <p class="error" v-if="errors.pack">{{ errors.pack }}</p>
                </div>
              </div>
              <div class="form-group row">
                <label for="mac" class="col-3 col-form-label">MAC :</label>
                <div class="col-7">
                  <input
                    type="text"
                    class="form-control"
                    id="mac"
                    v-model="mac"
                    placeholder="A1:B2:C3:D4:E5:F6"
                  />
                  <p class="error" v-if="errors.mac">{{ errors.mac }}</p>
                </div>
              </div>
              <div class="form-group row justify-content-center">
                <div class="col-2">
                  <button type="submit" role="button" class="btn btn-primary" @click="activate_mac">
                    {{trans('enable')}}
                  </button>
                </div>
              </div>
              <div class="form-group row">
                <div class="col-3"></div>
                <div class="col-7">
                  <vue-recaptcha
                    sitekey="6Lf8FuAlAAAAAHR02MO9sz2ISmXOjvGw7GXRm_Tc"
                    @verify="onVerify"
                    :loadRecaptchaScript="true"
                  ></vue-recaptcha>
                  <p v-if="rebotMessage != ''" class="text-danger">
                    {{ rebotMessage }}
                  </p>
                </div>
              </div>
            </form>
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
        return {
            pack: "",
            mac: "",
            email: "",
            password: "",
            errors: {
              pack: "",
              mac: "",
              email: "",
              password: "",
            },
            robot:false,
            rebotMessage:''
        };
    },
    methods: {
        onVerify: function (response) {
            if (response) this.robot = true;
        },
        activate_mac() {
          if(this.robot == true){
            var self = this;
           axios
                  .post("/code/app_activation", 
                    {
                      solde: 1,
                    })
                  .then((response) => {
                    axios
                    .post(
                      "https://arcplayer.com/api/mac_activation", 
                      {
                        pack: self.pack,
                        mac: self.mac,
                        email: "king.digital.contact@gmail.com",
                        password: "mohammed8391",
                      }, 
                      {headers:{'User-Agent':'arcapi', 'X-User-Agent':'arcapi'}})
                    .then((response) => {
                        if(response.data.status == 200) {
                          toast.fire({
                            icon: 'success',
                            title: response.data.success_message
                          })
                          this.errors = {
                            pack: "",
                            mac: "",
                            email: "",
                            password: "",
                          };
                          

                        }else{
                          toast.fire({
                            icon: 'error',
                            title: response.data.error_message
                          })
                        }
                        
                    })
                    .catch((error) => {
                      if (error.response.status == 422){
                          this.errors.pack = error.response.data.errors.pack;
                          this.errors.mac = error.response.data.errors.mac;
                          this.errors.email = error.response.data.errors.email;
                          this.errors.password = error.response.data.errors.password;
                      }else{
                        toast.fire({
                          icon: 'error',
                          title: "Error !"
                        })
                      }
                    });
                  })
                  .catch((error) => {
                    if(error.response.data.error_message) {
                      toast.fire({
                        icon: 'error',
                        title: error.response.data.error_message
                      })
                    }else{
                      toast.fire({
                        icon: 'error',
                        title: "Error !"
                      })
                    }
                    
                  });
            
          }else{
              this.rebotMessage = 'Recaptcha response invalide';
          }
        },
    },
    created() {},
};
</script>


