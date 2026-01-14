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
        <div class="row mb-4">
            <div class="col-md-12">  
                 <div class="alert alert-danger" role="alert" v-if="this.$userSolde == '0' && this.$userSoldeTest == '0' ">
                    <span>{{trans('no_balance')}} </span>
                </div>
                <div class="card">
                    <div class="card-header">
                    <div class="row">
                        <div class="col-lg-7 col-md-4 col-sm-4 col-xs-4"><h5>{{trans('manage_ac')}}</h5></div>
                        <div class="input-group col-lg-2 col-md-4 col-sm-4 col-xs-4">
                            <input                  
                            type="text"
                            class="form-control"
                            name="search"
                            v-model="searchQuery"
                            v-bind:placeholder="trans('search')"
                            />
                            <div class="input-group-append" @click="getResults()">
                            <span class="input-group-text bg-success"><i class="fa fa-search"></i></span>
                            </div>
                        </div>
                    </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover">
                            <thead><tr>
                                <th>{{trans('name')}}</th>
                                <th>{{trans('code_act')}}</th>
                                <th>{{trans('rem_time')}}</th>
                                <th>{{trans('mac_addr')}}</th>
                                <th>{{trans('days')}}</th>
                                <th>{{trans('status')}}</th>
                                <th>{{trans('action')}}</th>
                                                                

                            </tr>
                            </thead>
                            <tr>
                                <td colspan="10" class="text-center" v-if="onLoad">
                                    <div class="spinner-border" role="status">
                                        <span class="sr-only">{{trans('loading')}}</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="code in codes.data" :key="code.id">                            
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.name }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.number }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">
                                    <span v-if="code.time !== '' && today <= code.time">{{code.time }}</span>
                                    <span v-else-if="code.time !== ''&& today > code.time" style="color: red;"> {{trans('expired')}}</span>
                                </td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.mac }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.days }}</td>
                                 <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">
                                    <span v-if="code.time !== '' && today <= code.time" style="color: #2FA360;" >{{trans('activated')}}</span>
                                    <span v-else-if="code.time !== '' && today > code.time"  style="color: red;">{{trans('expired')}}</span>
                                    <span v-else  style="color: red;">{{trans('not_activ')}}</span>
                                </td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">
                                    <a  @click="activate(code.number)" :title="trans('enable')">
                                        <i class="fa fa-toggle-on mr-1 red-color"></i>
                                    </a>
                                </td>
                            </tr>
                        
                        </table>
                    </div>

                   <div class="card-footer">
                           <vue-paginate-al :totalPage="this.codes.last_page" activeBGColor="success" @btnClick="getpage" :withNextPrev="false"></vue-paginate-al>
                     </div>

                </div>
            </div>
        
        </div>

        

          <!-- Modal -->
        <div class="modal fade" id="addnew" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 v-if="editmode" class="modal-title" id="addnewLabel">{{trans('edit_code')}}</h5>
                <h5 v-else class="modal-title" id="addnewLabel">{{trans('new_code')}}</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <form @submit.prevent="editmode ? UpdateActiveCode() : CreateActiveCode()" enctype="multipart/form-data">
            <div class="modal-body">

                <div class="row form-group">
                        <label class="col-md-3 control-label">{{trans('code_length')}} :</label>
                        <div class="col-md-9">
                             <select  type="text" id="len" name="len" class="form-control" v-model="code.len">
                                <option  value="15">15</option>
                                <option  value="8">8</option>
                                <option  value="9">9</option>
                                <option  value="10">10</option>
                                <option  value="11">11</option>
                                <option  value="12">12</option>
                                <option  value="13">13</option>
                                <option  value="14">14</option>
                            </select>
                        </div>
                <p class="error" v-if="errors.len"> {{errors.len}}</p>
                </div>

                 <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('select_pack')}} :</label>

                    <div class="col-md-9">
                        <select  type="text" id="pack" name="pack" class="form-control" @change="onChange()" v-model="code.package_id">
                            <option  :value="pack.id" v-for="pack in packs" :key="pack.id">{{pack.package_name}}</option>
                     
                        </select>
                    </div>
                    <ul style=" columns: 3; -webkit-columns: 3;-moz-columns: 3;width:100%;list-style:none;margin-top:10px;">
                        <li v-for="bouquet in pack.all_bouquets" :key="bouquet" style="">
                            <input v-if="!editmode" type="checkbox" @change="onCheck(bouquet, pack)" :value="bouquet" class="pr-2" checked>
                            <input v-else type="checkbox" @change="onCheck(bouquet, pack)" :value="bouquet" class="pr-2" :checked="code.selected_bouquets.includes(bouquet)">
                            <label v-for="name in pack.names" :key="name.id" ><span v-if="name.id == bouquet">{{name.bouquet_name}}</span> </label>
                        </li>
                    </ul>
                 <p class="error" v-if="errors.pack"> {{errors.pack}}</p>
                </div>

                <div class="row form-group"> 
                    <label class="col-md-3 control-label">{{trans('code_act')}} :</label>

                    <div class="col-md-7">
                        <input type="text" name="number" class="form-control" v-model="code.number"  disabled="disabled">
                    </div>

                     <div class="col-md-2" v-show=" NumStart != ''">

                        <a v-on:click="getInput()"  class="btn btn-success">{{trans('generate')}}</a>

                    </div>
                 <p class="error" v-if="errors.number"> {{errors.number}}</p>
                </div>

                <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('cl_name')}} :</label>

                    <div class="col-md-9">
                        <input  type="text" name="name" class="form-control" v-model="code.name">
                    </div>

               <p class="error" v-if="errors.name"> {{errors.name}}</p>
                </div>
                

                  <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('notes')}} :</label>

                    <div class="col-md-9">
                        <textarea class="form-control" name="notes" rows="8" v-bind:placeholder="trans('search')" v-model="code.notes"></textarea>
                    </div>

                </div>
                
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal">{{trans('close')}}</button>

                <button v-if="editmode" type="submit" class="btn btn-success">{{trans('update')}}</button>
                <button v-else  type="submit" class="btn btn-primary">{{trans('create')}}</button>
            </div>
        </form>

            </div>
            
        </div>
        </div>

        
        <!--___________________________________________________ Modal download playlist channel ___________________________________________-->

        <!-- Modal -->
            <div class="modal fade" id="download" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle">{{trans('down_m3u')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row form-group">
                            <label class="col-md-3 control-label">{{trans('select_type')}} :</label>

                            <div class="col-md-9">
                                <select  type="text" id="type" name="type" class="form-control" @change="onChangeM3U()"  v-model="type">

                                    <option  value="m3u">m3u</option>
                                    <option  value="m3u_plus">m3u_plus</option>
                                </select>
                            </div>

                    </div>

                     

                <div class="row form-group" >

                    <div class="col-md-10" v-show="Show_m3u_plus == true" >
                        <input type="text" v-bind="m3u_plus" name="m3u_plus" id="m3u_plus" class="form-control" v-model="m3u_plus" >
                        
                    </div>

                    <div class="col-md-2" v-show="Show_m3u_plus  == true">
                        <span class="btn btn-info text-white copy-btn ml-auto" @click.stop.prevent="copyTestingCode">{{trans('copy')}}</span>
                    </div>

                    <div class="col-md-10" v-show="Show_m3u  == true">
                        <input type="text" v-bind="m3u" name="m3u" id="m3u" class=" form-control" v-model="m3u" >
                    </div>

                      <div class="col-md-2" v-show="Show_m3u  == true">
                        <span class="btn btn-info text-white copy-btn ml-auto" @click.stop.prevent="copyTestingCode">{{trans('copy')}}</span>
                    </div>
                </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{trans('close')}}</button>
                    <a v-show="Show_m3u" target="_blank" :href="m3u" class="btn btn-primary">{{trans('download')}}</a>
                     <a v-show="Show_m3u_plus" target="_blank" :href="m3u_plus" class="btn btn-primary">{{trans('download')}}</a>
                </div>
                </div>
            </div>
            </div>

      
       
    </div>

</template>



<script>


    export default {
         mounted() {
               Vue.prototype.$userType = document.querySelector("meta[name='user-type']").getAttribute('content');
               Vue.prototype.$userSolde = document.querySelector("meta[name='user-solde']").getAttribute('content');

           this.ShowActiveCode(); 
                Fire.$on('AfterCreate' , () => {
                        this.ShowActiveCode(); 
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
        current_solde_test:0
      }
    },

        methods: {

            generatePassword() {
                // Generate 15-character password with uppercase, lowercase, and numbers
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
                let password = '';
                for (let i = 0; i < 15; i++) {
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }
                return password;
            },

              //------------------------------------------DELETE FUNCTION ----------------------------------\\

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

            copyTestingCode() {
                if(this.Show_m3u){

                   let testingCodeToCopy = document.querySelector('#m3u')
                        testingCodeToCopy.setAttribute('type', 'text')
                        testingCodeToCopy.select()
                         document.execCommand('copy');
                }else {
                    let testingCodeToCopy = document.querySelector('#m3u_plus')
                        testingCodeToCopy.setAttribute('type', 'text')
                        testingCodeToCopy.select()
                         document.execCommand('copy');
                }
                            
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
                axios.post('iactive/activate', {
                    a:number,
                    m:''
                })
                .then(response => {
                    toast.fire({
                        icon: 'success',
                        title: response.data.message
                    })
                    this.ShowActiveCode();
                }).catch(error=>{
                });
            },

            getpage(page = 1) {
                axios.get('iactive/acivecodes?page=' + page)
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
                axios.get('iactive/acivecodes').then(({ data }) => (this.codes = data, this.onLoad = false));
            
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
            // Event listener only - API calls already in mounted()
        }
    }
</script>


