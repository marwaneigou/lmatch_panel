<style>
    .error {
        color: red;
    }
    .swal2-title{
        font-size: 1.125em !important;
    }
</style>

<template>

    <div class="container-fluid">
        <div class="row mb-4 d-flex justify-content-center">
            <div class="col-md-8 "> 
                <div class="card">
                    <div class="card-header"> 
                    </div>
                    <div class="card-body"> 
                        <form @submit.prevent= "activate()">
                            <div class="form-group row">
                                <label for="code" class="col-3 col-form-label">{{trans('enter_code')}} :</label>
                                <div class="col-7">
                                    <input type="text" class="form-control" id="code" v-model="code" placeholder="Votre code">
                                </div>
                                <div class="col-2">
                                    <button type="submit" role="button" class="btn btn-primary">{{trans('enable')}}</button>
                                </div>
                            </div>
                            <div class="form-group row">
                                <div class="col-3"></div>
                                <div class="col-7">
                                    <vue-recaptcha  sitekey="6Lf8FuAlAAAAAHR02MO9sz2ISmXOjvGw7GXRm_Tc" @verify="onVerify" :loadRecaptchaScript="true"></vue-recaptcha>
                                    <p v-if="rebotMessage != ''" class="text-danger">{{rebotMessage}}</p>
                                </div>
                                
                            </div>
                        </form>
                        
                    </div>
                    <div class="card-body">
                        <label v-if="message != ''">{{message}}</label>
                        <div v-if="showM3uButton" class="row">
                            <div class="col-12"><a target="_blank" :href="m3u_plus" class="btn btn-primary">{{trans('download')}} M3U Plus</a></div>
                        </div>
                        <div v-if="showM3uButton" class="row">
                            <div class="col-12 text-bold mt-3">
                                <div class="col-12">{{trans('down_link')}} : <br><br> <input type="text" class="col-12" id="m3u_plus_active" v-model="m3u_plus" style="border:none" readonly></div>
                                <div class="col-4">
                                    <span class="btn btn-info text-white copy-btn ml-auto" @click.stop.prevent="copyTestingCode">{{trans('copy')}}</span>   
                                </div>
                                                             
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</template>

<style>
#m3u_plus_active:focus{
    border-color:white;
}
</style>

<script>

    import VueRecaptcha from 'vue-recaptcha';
    export default {
        components: { VueRecaptcha },
         mounted() {
               axios.defaults.withCredentials = true;
               Vue.prototype.$userType = document.querySelector("meta[name='user-type']").getAttribute('content');
               Vue.prototype.$userSolde = document.querySelector("meta[name='user-solde']").getAttribute('content');

                Fire.$on('AfterCreate' , () => {
                    }); 
       },
        data () {
        return {
            
            today : new Date().toISOString().slice(0, 10),
            tt: this.$userSolde,
            type:'',
            m3u:'',
            m3u_plus:'',
            Show_m3u :false,
            Show_m3u_plus: false,
            searchQuery:'',
            editmode: false,
            codes:{},
            kk:'',
            packs:[],
            pack:{
                id:'',
                package_name:'',
                official_duration:'',
                bouquets:'',
                official_duration_in:'',
                trial_duration:'',
                trial_duration_in:'',
                is_trial:'',
            },

            NumStart:'',
            validationErrors: '',

            code : {
                id:'',
                len: 15,
                number: '',
                pack:'',
                name:'',
                days:'',
                duration:'',
                mac:'',
                time:'',
                notes:'',
                res:'',
                package_id:'',
            },

            errors: {
                len:'',
                number:'',
                name:'',
                pack:'',
            },
            onLoad:true,
            selected_bouquet:[],
            current_solde:0,
            current_solde_test:0,
            code:'',
            message:'',
            showM3uButton:false,
            robot:false,
            rebotMessage:''
      }
    },

        methods: {

              //------------------------------------------DELETE FUNCTION ----------------------------------\\
            
            copyTestingCode() {

                    let testingCodeToCopy = document.querySelector('#m3u_plus_active')
                        testingCodeToCopy.setAttribute('type', 'text')
                        testingCodeToCopy.select()
                         document.execCommand('copy');

            },

            generatePassword() {
                // Generate 15-character password with uppercase, lowercase, and numbers
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                let password = '';
                for (let i = 0; i < 15; i++) {
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return password;
            },
            onVerify: function (response) {
                if (response) this.robot = true;
            },
            Renew(number,package_id){
                axios({
                    url: 'code/getSolde',
                    method: 'POST',
                }).then((response) => {
                    if(response.data.solde > 0  || this.$userType == "Admin") {
                        Swal.fire({
                            title: 'Êtes-vous sûr de renouveler? Un point sera déduit de votre solde lors du renouvellement',
                            text: "هل أنت متأكد من التجديد؟ سيتم خصم نقطة من رصيدك عند التجديد",
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Oui',
                            cancelButtonText: 'Non'
                        }).then((result) => {
                            
                            if (result.value) {
                                axios.put('code/renew/'+number, {
                                    code: number,
                                    package_id: package_id
                                    })
                                .then(() => {
                                
                                    Swal.fire(
                                    'Renew!',
                                    'this code renew with success.',
                                    'success'
                                    )
                                
                                Fire.$emit('AfterCreate');
                                }).catch(() => {
                                    Swal.fire(
                                    'Failed!',
                                    'There was something wronge',
                                    'warning'
                                    )
                                })
                            }
                        })
                    }else{
                        toast.fire({
                            icon: 'error',
                            title: 'Votre solde de test est insuffissant !'
                        })
                    }
                }); 
                        
            },


            onChangeM3U(value) {

                if(event.target.value === 'm3u'){
                    this.Show_m3u = true;
                    this.Show_m3u_plus = false;
                
                }else {
                    this.Show_m3u = false;
                    this.Show_m3u_plus = true;
                }

            },

            showModalDownload(number){
                     $('#download').modal('show');
                     
                    this.type ='';
                    this.Show_m3u = false;
                    this.Show_m3u_plus = false;

                    this.m3u = '';
                    this.m3u_plus = '';

                        axios.post('code/showM3U', {
                            code:number
                        })
                        
                        .then(response => {

                                this.m3u = response.data+'&type=m3u&output=ts';
                                this.m3u_plus = response.data+'&type=m3u_plus&output=ts';

                            }).catch(error => {
                                
                            })
                    
                    
                      
                },

            activate(number) {
                if(this.robot == true){
                    this.rebotMessage = '';
                    axios.post('active/activate', {
                        a:this.code,
                        m:''
                    })
                    .then(response => {
                        if(response.data.status == 100) {
                            this.message = response.data.message;
                            this.showM3uButton = true;
                            axios.post('code/showM3U', {
                                code:this.code
                            })
                            
                            .then(response => {

                                this.m3u = response.data+'&type=m3u&output=ts';
                                this.m3u_plus = response.data+'&type=m3u_plus&output=ts';

                            }).catch(error => {
                                
                            })
                        }else{
                            if(response.data.status == 550) {
                                this.message = response.data.message;
                            }
                        }
                    }).catch(error=>{
                    });
                }else{
                    this.rebotMessage = 'Recaptcha response invalide';
                }
            },

            getpage(page = 1) {
                axios.post('active/acivecodes?page=' + page)
                    .then(response => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
            },
            ResetMac(id){
                Swal.fire({
                    title: 'Alert',
                    text: "Voulez-vous vraiment réinitialiser l'adresse MAC",
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Oui',
                    cancelButtonText: 'Non'
                }).then((result) => {
                    if (result.value) {
                        axios.put('ActiveCode/resetMac/'+id , this.code.mac)
                        .then(response => {
                            Fire.$emit('AfterCreate');
                            toast.fire({
                                icon: 'success',
                                title: 'Mac Adress reset in successfully'
                            })
                            
                        }).catch(error => { })
                    }
                })
                
            
            },

            getPackiD(value) {
                if(!this.editmode) {
                    axios.post('code/GetPackID/'+value).then(({ data }) => (this.pack = data, this.selected_bouquet = this.pack.custom_bouquets));
                }else {
                    axios.post('code/GetPackID/'+value).then(({ data }) => (this.pack = data, this.pack.custom_bouquets = this.code.selected_bouquets));
                }
                 
            },

             getPack() {
                 axios.post('code/GetPack').then(({ data }) => (this.packs = data));
            },

            
            getResults(page = 1) {
                
                axios.post('code/acivecodes_all?page=' + page+'&query='+this.searchQuery)
                    .then(response => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });

            },
            newModel(){
               
                $('#addnew').modal('show');
                 this.resetForm();
                this.editmode = false;
                this.code.len = 15;
            },

            editModel(code){
                $('#addnew').modal('show');
                this.resetForm();
                this.code = code;
                this.editmode = true
                this.getPackiD(code.package_id);

                  if(code.is_trial){

                    this.NumStart = "160";
                    
           
                 
               


                
                }else {
                      this.NumStart = "190";
                }
            },

          

            onChange(value) {

               this.getPackiD(event.target.value);


                if(this.pack.is_trial){
                   
                    this.NumStart = "160";
                        if(!this.editmode){
                            this.code.res = '';
                            this.code.number = '';
                        }
                
                }else {
                    
                      this.NumStart = "190";
                        if(!this.editmode){
                            this.code.res = '';
                            this.code.number = '';
                        }
                }

            },


            onCheck(bouquet, p) {
                var index = -1;
                for (let k = 0; k < p.custom_bouquets.length; k++) {
                    const element = p.custom_bouquets[k];
                    if(element == bouquet) {
                        index = k;
                    }
                }
                
                if(index != -1) {
                    p.custom_bouquets.splice(index, 1);
                }else{
                    p.custom_bouquets.push(bouquet);
                }

                this.selected_bouquet = p.custom_bouquets;
            },

            getImgUrl(pet) {
                var images = '/assets/img/flags/' + pet + ".png"
                return images
            },

            getInput: function () {
                this.getRandomNumber();
            },

            getRandomNumber: function () {
                this.kk = this.generateNumber(this.code.len);
                this.code.res = this.kk.toString();
                this.code.number = this.NumStart.concat(this.code.res).toString();
            },

            generateNumber(len){
    

                var test = len -3 ;
                return Math.floor(Math.pow(10, test-1) + Math.random() * (Math.pow(10, test) - Math.pow(10, test-1) - 1));
                
            },   

            resetForm(){
                this.code =  {
                    id:'',
                    len: '',
                    number: '',
                    pack:'',
                    name:'',
                    days:'',
                    duration:'',
                    mac:'',
                    time:'',
                    notes:'',
                    res:'',
                    package_id:'',
                },
                this.NumStart= '',

                this.pack= {
                    id:'',
                    package_name:'',
                    official_duration:'',
                    bouquets:'',
                    official_duration_in:'',
                    is_trial:'',
                    trial_duration:'',
                    trial_duration_in:'',
                }
          },  

            //------------------------------------------show Active Code FUNCTION ----------------------------------\\

            ShowActiveCode (){
                axios.post('active/acivecodes').then(({ data }) => (this.codes = data, this.onLoad = false));
            
            },
          //------------------------------------------CREATE FUNCTION ----------------------------------\\

            CreateActiveCode() { 

                axios({
                    url: 'code/getSolde',
                    method: 'POST',
                }).then((response) => {
                    if(this.pack.is_trial === 1) {
                        if(response.data.solde_test > 0 || this.$userType == "Admin") {
                            axios.post('code/acivecodes', {
                                len : this.code.len,
                                number: this.code.number,
                                mac: this.code.mac,
                                pack: this.code.package_id,
                                name: this.code.name,
                                time: this.code.time,
                                duration: this.pack.official_duration,
                                duration_in: this.pack.official_duration_in,
                                username: this.code.res,
                                notes: this.code.notes,
                                bouquets:JSON.stringify(this.selected_bouquet),
                                custom_bouquets: JSON.stringify(this.selected_bouquet),
                                is_trial : this.pack.is_trial,
                                trial_duration:  this.pack.trial_duration,
                                trial_duration_in:  this.pack.trial_duration_in,
                                password: Math.random().toString(36).substr(2, 10) , 
                            
                            }).then(response => {
                                Fire.$emit('AfterCreate');
                                toast.fire({
                                    icon: 'success',
                                    title: 'Active Code created in successfully'
                                })
                                $('#addnew').modal('hide');
                            }).catch(error => {
                                    if (error.response.status == 422){

                                        this.errors.len = error.response.data.errors.len;
                                        this.errors.name = error.response.data.errors.name;
                                        this.errors.number = error.response.data.errors.number;
                                        this.errors.pack = error.response.data.errors.pack;

                                    }
                            })
                        }else{
                            toast.fire({
                                icon: 'error',
                                title: 'Votre solde de test est insuffissant !'
                            })
                            $('#addnew').modal('hide');
                        }
                    }else{
                        if(response.data.solde > 0 || this.$userType == "Admin") {
                            axios.post('code/acivecodes', {
                                len : this.code.len,
                                number: this.code.number,
                                mac: this.code.mac,
                                pack: this.code.package_id,
                                name: this.code.name,
                                time: this.code.time,
                                duration: this.pack.official_duration,
                                duration_in: this.pack.official_duration_in,
                                username: this.code.res,
                                notes: this.code.notes,
                                bouquets:JSON.stringify(this.selected_bouquet),
                                custom_bouquets: JSON.stringify(this.selected_bouquet),
                                is_trial : this.pack.is_trial,
                                trial_duration:  this.pack.trial_duration,
                                trial_duration_in:  this.pack.trial_duration_in,
                                password: this.generatePassword(),
                            
                            }).then(response => {
                                Fire.$emit('AfterCreate');
                                toast.fire({
                                    icon: 'success',
                                    title: 'Active Code created in successfully'
                                })
                                $('#addnew').modal('hide');
                            }).catch(error => {
                                    if (error.response.status == 422){

                                        this.errors.len = error.response.data.errors.len;
                                        this.errors.name = error.response.data.errors.name;
                                        this.errors.number = error.response.data.errors.number;
                                        this.errors.pack = error.response.data.errors.pack;

                                    }
                            })
                        }else{
                            toast.fire({
                                icon: 'error',
                                title: 'Votre solde est insuffissant !'
                            })
                             $('#addnew').modal('hide');
                        }
                    }
                });   
   
                
            
            },

          //------------------------------------------UPDATE FUNCTION ----------------------------------\\

            UpdateActiveCode() { 
                const headers={headers:{'Content-Type':'multipart/form-data'}};
                axios.put('code/acivecodes/'+this.code.id,{
                    len : this.code.len,
                    number: this.code.number,
                    mac: this.code.mac,
                    pack: this.code.package_id,
                    name: this.code.name,
                    time: this.code.time,
                    duration: this.pack.official_duration,
                    duration_in: this.pack.official_duration_in,
                    username: this.code.res,
                    notes: this.code.notes,
                    bouquets: JSON.stringify(this.selected_bouquet),
                    is_trial : this.pack.is_trial,
                    trial_duration:  this.pack.trial_duration,
                    trial_duration_in:  this.pack.trial_duration_in,
                })
                   .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Active Code Updated in successfully'
                        })
                        $('#addnew').modal('hide');
                    }).catch(error => {
                             if (error.response.status == 422){
                                this.errors.len = error.response.data.errors.len;
                                this.errors.name = error.response.data.errors.name;
                                this.errors.number = error.response.data.errors.number;
                                this.errors.pack = error.response.data.errors.pack;

                             }
                    })
            
            },

            
            EnabledActiveCode(number) {
                axios.post('code/ActiveD/'+number)
                    .then(response => {
                        Fire.$emit('AfterCreate');
                    });
            },

        //------------------------------------------DELETE FUNCTION ----------------------------------\\

            DELETEActiveCode(id){
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    type: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes'
                }).then((result) => {
                    if (result.value) {
                        axios.delete('code/acivecodes/'+id)
                        .then(() => {
                            Swal.fire(
                            'Disabled!',
                            'this code has been disabled.',
                            'success'
                            )
                        Fire.$emit('AfterCreate');
                        }).catch(() => {
                            Swal.fire(
                            'Failed!',
                            'There was something wronge',
                            'warning'
                            )
                        })
                    }
                })
            },




        },

         created() {
            
            Fire.$on('AfterCreate' , () => {
                }); 
        
        }
    }
</script>


