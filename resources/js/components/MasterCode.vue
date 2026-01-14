<template>

    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">  
                <div class="alert alert-danger" role="alert" v-if="this.$userSolde == '0'">
                    <span>{{trans('no_balance')}} </span>
                </div>
                <div class="card">
                    <div class="card-header">
                    <div class="row">
                            <div class="col-12">
                                <button
                                class="btn btn-success c_btn mx-2"
                                @click="show_filter = !show_filter"
                                >                  
                                <i class="fa fa-filter" aria-hidden="true"></i>
                                </button>
                                <button
                                v-show="this.$userSolde != '0' || this.$userSoldeTest != '0'"
                                class="btn btn-success c_btn"
                                @click="newModel()"
                                >                  
                                <i class="fa fa-plus fa-fw"></i>
                                </button>
                            </div>
                            <div class="container col-12 bg-white p-3 border mt-2" v-if="show_filter">
                                <div class="row">
                                    <div class="input-group col-lg-4 col-md-4 col-sm-12 col-xs-12 col-12 mb-2">
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
                         </div>
                         </div>
                    
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover">
                            <thead><tr>
                                <th>{{trans('order')}}</th>
                                <th v-if="user_type == 'Admin'">{{trans('owner')}}</th>
                                <th>{{trans('name')}}</th>
                                <th>{{trans('code_act')}}</th>
                                <th>{{trans('rem_time')}}</th>
                                <th>{{trans('mac_addr')}}</th>
                                <th>{{trans('days')}}</th>
                                <th>{{trans('status')}}</th>
                                <th>{{trans('action')}}</th>
                            </tr>
                            </thead>
                            <tbody> 
                            <tr>
                                <td colspan="10" class="text-center" v-if="onLoad">
                                    <div class="spinner-border" role="status">
                                        <span class="sr-only">{{trans('loading')}}</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="code in codes.data" :key="code.id">
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.id }}</td>
                                <td v-if="user_type == 'Admin'" :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.user ? code.user.name : '' }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.name }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.number }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">
                                    <span v-if="code.time !== '' && today <= code.time">{{code.time }}</span>
                                    <span v-else-if="code.time !== ''&& today > code.time" style="color: #DC143C;">{{trans('expired')}}</span>
                                </td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.mac }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-if="$userType !='Admin'">{{code.days }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-else><input type="text" class="form-control" @keyup="changeDays($event, code.id)" :value="code.days"></td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">
                                    <span v-if="code.time !== '' && today <= code.time" style="color: #2FA360;" >{{trans('activated')}}</span>
                                    <span v-else-if="code.time !== '' && today > code.time"  style="color: #DC143C;">{{trans('expired')}}</span>
                                    <span v-else  style="color: #DC143C;">{{trans('not_activ')}}</span>
                                </td>
                               
                                <td  v-if="code.enabled == 1">
                                    <a  @click="editModel(code)" :title="trans('edit')">
                                        <i class="fa fa-pencil blue-color mr-1" style="color: blueviolet;"></i>
                                    </a>
                                     
                                    <a  @click="showModalDownload(code.number)"  :title="trans('download')">
                                        <i class="fa fa-arrow-circle-o-down mr-1" style="color: blueviolet;"></i>
                                    </a>

                                    
                                    <a   v-show="$route.meta[0].content != '0' && code.time !== ''" @click="Renew(code.number)"  :title="trans('renew')" >
                                        <i class="fa fa-refresh mr-1" style="color: blueviolet"></i>
                                    </a>

                                     
                                    <a  @click="DELETEMasterCode(code.number, code.mac, 'disabled')" :title="trans('disable')">
                                        <i class="fa fa-toggle-on mr-1" style="color: green;"></i>
                                    </a>

                                    <a  v-show="$userType == 'Admin'" @click="DELETEMasterCode(code.number, code.mac, 'delete')" :title="trans('delete')">
                                        <i class="fa fa-trash-o mr-1" style="color:#DC143C;"></i>
                                    </a>
                                    
                                </td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-else>
                                    <a  @click="enableMastercode(code.number, code.mac)" :title="trans('enable')">
                                        <i class="fa fa-toggle-on mr-1 red-color"></i>
                                    </a>
                                </td>
                            </tr>
                        
                            </tbody>
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
        <form @submit.prevent="editmode ? UpdateMasterCode() : sendRequest()" enctype="multipart/form-data">
            <div class="modal-body">

                <div class="row form-group">
                        <label class="col-md-3 control-label">{{trans('code_length')}} :</label>

                        <div class="col-md-9">
                             <select  type="text" id="len" name="len" class="form-control" v-model="code.len">
                                <option  value="15">15</option>
                                <option  value="8">8</option>
                                <option  value="9">9</option>
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
                        <select  type="text" id="type" name="pack" class="form-control" @change="onChange()" v-model="code.package_id">
                            <option  :value="pack.id" v-for="pack in packs" v-bind:key="pack.id">{{pack.package_name}}</option>
                     
                        </select>
                    </div>

                    

                 <p class="error" v-if="errors.pack"> {{errors.pack}}</p>
                </div>

                <div class="row form-group"> 
                    <label class="col-md-3 control-label">{{trans('code_act')}} :</label>

                    <div class="col-md-9">
                        <input type="text" name="number" class="form-control" v-model="code.number">
                    </div>
                    <p class="error" v-if="errors.number"> {{errors.number}}</p>
                </div>

                <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('mac_addr')}} :<span v-show="!editmode" class="error">({{trans('max')}} 20k)</Span></label>

                    <div class="col-md-9" v-if="editmode">
                        <input type="text" name="mac" class="form-control" v-model="code.mac">
                    </div>

                     <div class="col-md-9" v-else>
                        <textarea @keyup="convert()" class="form-control" name="mac[]" rows="8" placeholder="Enter your adress Mac" v-model="code.mac"></textarea>
                    </div>

                    <p class="error" v-if="errors.mac"> {{errors.mac}}</p>
                    <p class="error" v-if="errors.myarray"> {{errors.myarray}}</p>
                    <p class="ml-5 success" v-show="!editmode"> {{myarray.length}} {{trans('mac_addr')}}</p> 

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

                <div v-show="editmode" style="margin-left: 13px;color: cornflowerblue;"> 
                        <input  type="checkbox" name="check" v-model="code.check" ><span> Check This if you want applique change for this Master Code</span>
                    </div>

                
            </div>
            <div class="modal-footer" v-show="showbtn">
                <button type="button" class="btn btn-danger" data-dismiss="modal">{{trans('close')}}</button>

                <button v-if="editmode" type="submit" class="btn btn-success">{{trans('update')}}</button>
                <button  v-else  type="submit" class="btn btn-primary">{{trans('create')}}</button>
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
           this.getPack();
           this.ShowMasterCode(); 
                Fire.$on('AfterCreate' , () => {
                        this.ShowMasterCode(); 
                        this.getPack();
                        }); 
       },
        
        data () {
        return {
            
            today : new Date().toISOString().slice(0, 10),
            user_type:this.$userType,
            type:'',
            m3u:'',
            m3u_plus:'',
            Show_m3u :false,
            Show_m3u_plus: false,

            showbtn:true,
            myarray:[],
           searchQuery:'',
            editmode: false,
            codes:{},
            kk:'',
            code : {
                id:'',
                len: '',
                number: '',
                package_id:'',
                name:'',
                days:'',
                mac:[],
                time:'',
                res:'',
                notes:'',
                check:''
            },

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
            errors: {
                len:'',
                number:'',
                name:'',
                pack:'',
                mac:'',
                myarray:''
            },
            onLoad:true,
            show_filter: false
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

               Renew(code){
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "you want to renew this code 1 year",
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, Renew it!'
                            }).then((result) => {
                            
                                if (result.value) {
                            
                                    axios.put('codemaster/renew/'+code, code)
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

            getpage(page = 1) {
                axios.post('code/mastercodes_all?page=' + page)
                    .then(response => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
            },

            convert(){
                this.myarray = this.code.mac.split('\n');
            },

             ResetMac(id){
                axios.put('Mastercode/resetMac/'+id , this.code.mac)
                    .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Mac Adress reset in successfully'
                        })
                        
                        }).catch(error => {
                        
                        })
            
            },

            getPackiD(value) {
                 axios.post('code/GetPackID/'+value).then(({ data }) => (this.pack = data));
            },

             getPack() {
                 axios.post('code/GetPack').then(({ data }) => (this.packs = data));
            },

            getUserAuth() {
                axios.post('code/GetUserAuth')
                    .then(response => {
                        this.this.$userType = response.data.this.$userType;
                        this.this.$userSolde = response.data.this.$userSolde;
                    });
            },

             getResults(page = 1) {
                axios.post('code/mastercodes_all?page=' + page+'&query='+this.searchQuery)
                    .then(response => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });

            },
            newModel(){
               
                $('#addnew').modal('show');
                 this.resetForm();
                this.editmode = false;
            },

            editModel(code){
        
                
                $('#addnew').modal('show');
                this.resetForm();
                this.code = code;
                this.editmode = true;
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

            getInput: function () {
                
                this.getRandomNumber()
            },

            getRandomNumber: function () {
                if($('#type').val() == 185) {
                    this.kk = this.generateNumber(this.code.len-3);
                    this.code.res = this.kk.toString();
                    let st = "185001"
                    this.code.number = st.concat(this.code.res).toString();
                }else{
                    this.kk = this.generateNumber(this.code.len);
                    this.code.res = this.kk.toString();
                    this.code.number = this.NumStart.concat(this.code.res).toString();
                }
            },

            generateNumber(len){
    


                 var test = len -3 ;
                return Math.floor(Math.pow(10, test-1) + Math.random() * (Math.pow(10, test) - Math.pow(10, test-1) - 1));

            },   

            resetForm(){
                this.kk= '',             
                this.searchQuery='',
                this.NumStart='',
                this.myarray='',
                this.code =  {
                    id:'',
                    len: '',
                    number: '',
                    package_id:'',
                    name:'',
                    days:'',
                    mac:[],
                    time:'',
                    res:'',
                    notes:''
                },
                this.errors= {
                    len:'',
                    number:'',
                    name:'',
                    pack:'',
                    mac:''
                }
                
          },  

            //------------------------------------------show Active Code FUNCTION ----------------------------------\\

            ShowMasterCode (){
                axios.post('code/mastercodes_all').then(({ data }) => (this.codes = data, this.onLoad = false));
            
            },
          //------------------------------------------CREATE FUNCTION ----------------------------------\\

            sendRequest() { 
                
             
                axios.post('code/getrequest', {
                    len : this.code.len,
                    number: this.code.number,
                    myarray: this.myarray,
                     mac: this.code.mac,
                    name: this.code.name,
                   
                    }).then(response => {
                            this.CreateMasterCode();
                             this.$Progress.start()
                             Swal.fire({
                                title: 'Please Wait , Loading',
                                text: "do not leave this window",
                                icon: 'warning',
                                showConfirmButton: false,

                            });
                             this.showbtn = false;
                         
                    }).catch(error => {
                        if (error.response.status == 422){

                                this.errors.len = error.response.data.errors.len;
                                this.errors.name = error.response.data.errors.name;
                                this.errors.number = error.response.data.errors.number;
                                this.errors.mac = error.response.data.errors.mac;
                                this.errors.myarray = error.response.data.errors.myarray;

                             }
                    })
            
            },


            CreateMasterCode() { 
                
               
             
                axios.post('code/mastercodes', {
                    len : this.code.len,
                    number: this.code.number,
                    myarray: this.myarray,
                     mac: this.code.mac,
                    pack: this.code.package_id,
                    name: this.code.name,
                    time: this.code.time,
                    duration: this.pack.official_duration,
                    duration_in: this.pack.official_duration_in,
                    username: this.code.res,
                    notes: this.code.notes,
                    bouquets: this.pack.bouquets,
                    is_trial : this.pack.is_trial,
                    trial_duration:  this.pack.trial_duration,
                    trial_duration_in:  this.pack.trial_duration_in,
                    password: this.generatePassword(),

                    }).then(response => {
                         if(response.data.finish){
                            Fire.$emit('AfterCreate');
                            
                            toast.fire({
                                icon: 'success',
                                title: 'Master Code created in successfully'
                            })
                            this.showbtn = true;
                             $('#addnew').modal('hide');
                             this.$Progress.finish();
                         }
                    }).catch(error => {
                        if (error.response.status == 422){

                                this.errors.len = error.response.data.errors.len;
                                this.errors.name = error.response.data.errors.name;
                                this.errors.number = error.response.data.errors.number;
                                this.errors.pack = error.response.data.errors.pack;
                                this.errors.mac = error.response.data.errors.mac;
                                this.errors.myarray = error.response.data.errors.myarray;

                             }
                    })
            
            },

          //------------------------------------------ UPDATE FUNCTION ----------------------------------\\

            UpdateMasterCode() { 
                const headers={headers:{'Content-Type':'multipart/form-data'}};
                axios.put('code/mastercodes/'+this.code.id , 
                {
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
                        bouquets: this.pack.bouquets,
                        is_trial : this.pack.is_trial,
                        trial_duration:  this.pack.trial_duration,
                        trial_duration_in:  this.pack.trial_duration_in,
                        check : this.code.check,

                   }).then(response => {
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

        //------------------------------------------DELETE FUNCTION ----------------------------------\\

            DELETEMasterCode(number, mac, type){
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
                    
                            axios.delete('code/mastercodesDelete/'+number+'/'+mac+'/'+type)
                            .then(() => {
                                if(type == 'disabled') {
                                    Swal.fire(
                                        'Disabled!',
                                        'this code has been disabled.',
                                        'success'
                                    )
                                }else{
                                    Swal.fire(
                                        'Deleted!',
                                        'this code has been deleted.',
                                        'success'
                                    )
                                }
                                
                            
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

            enableMastercode(number, mac) {
                axios.post('code/enableMastercode/'+number+'/'+mac)
                    .then(response => {
                        Fire.$emit('AfterCreate');
                    });
            },

            changeDays(e, id) {
                axios.post('changeDays/mastercode/'+id, {
                    days:e.target.value
                })
                    .then(response => {
                        Fire.$emit('AfterCreate');
                    });
            },


        },

         created() {
            // Event listener only - API calls already in mounted()
        }
    }
</script>
