<style>
    .error {
        color: red;
    }
    hr.style10 {
	border-top: 1px dotted #8c8b8b;
	border-bottom: 1px dotted #fff;
    }
</style>


<template>

    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">  
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
                            <button v-if="userTypee =='Admin'" class="btn btn-success c_btn"  @click="newModel()">
                                <i class="fa fa-plus fa-fw"></i>
                            </button>
                            <button v-else class="btn btn-success c_btn"  @click="newModel()">
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
                                <th>{{trans('owner')}}</th>
                                <th>{{trans('username')}}</th>
                                <th>{{trans('phone')}}</th>
                                <th>{{trans('email')}}</th>
                                <th>{{trans('solde')}}</th>
                                <th>{{trans('bal_test')}}</th>
                                <th>{{trans('gift')}}</th>
                                <th>{{trans('recharge')}}</th>
                                <th v-if="userTypee != 'Admin'">{{trans('recover_crd')}}</th>
                                <th>{{trans('type')}}</th>
                                <th>{{trans('block')}}</th>
                                <th>{{trans('action')}}</th>
                            </tr>
                             </thead>
                            <tbody>
                            <tr>
                                <td colspan="12" class="text-center" v-if="onLoad">
                                    <div class="spinner-border" role="status">
                                        <span class="sr-only">{{trans('loading')}}</span>
                                    </div>
                                </td>
                            </tr> 
                            <tr v-for="user in users.data" :key="user.id">
                                <td>{{user.id}}</td>
                                <td>{{user.owner}}</td>
                                <td>{{user.name}}</td>
                                <td>{{user.phone}}</td>
                                <td>{{user.email}}</td>
                                <td>{{user.solde}}</td>
                                <td>{{user.solde_test}}</td>
                                <td>{{user.gift}}</td>
                                <td>
                                    <button v-show="user.type != 'Admin'" type="button" class="btn btn-primary" @click="editSolde(user)">
                                        {{trans('recharge')}}
                                    </button>
                                </td>
                                <td v-if="userTypee != 'Admin'">
                                    <button type="button" class="btn btn-primary" @click="recoverSolde(user)">
                                        {{trans('recover')}}
                                    </button>
                                </td>
                                <td>{{user.type}}</td>
                                <td> 
                                    <a v-if="user.type != 'Admin' && (user.blocked == '' || user.blocked == null) " @click="block(user.id)" :title="trans('block')">
                                        <i class="fa fa-toggle-off" style="color:gray;"></i>
                                    </a>
                                    <a v-if="user.type != 'Admin' && (user.blocked != '' && user.blocked != null) " @click="unblock(user.id)" :title="trans('unlock')">
                                        <i class="fa fa-toggle-on mr-1" style="color:green;"></i>
                                    </a>
                                </td>
                                <td>
                                    <a  @click="editModel(user)"  :title="trans('edit')">
                                        <i class="fa fa-pencil blue-color mr-1 mr-1" style="color: blueviolet;"></i>
                                    </a>
                                    <a @click="showChart(user.id)"  title="chart" v-show="$userType === 'Admin'">
                                        <i class="fa fa-line-chart" style="color: blueviolet"></i>
                                    </a>
                                    <a @click="DELETEResiler(user.id)"  :title="trans('delete')">
                                        <i class="fa fa-trash-o mr-1 mr-1" style="color: #DC143C"></i>
                                    </a>
                                </td>
                            </tr>
                        
                            </tbody>
                        </table>
                    </div>

                      <div class="card-footer">
                            <vue-paginate-al :totalPage="this.users.last_page" activeBGColor="success" @btnClick="getpage" :withNextPrev="false"></vue-paginate-al>
                    </div>

                </div>
            </div>
        
        </div>

        

    <!--________________________________________________  Modal of CRUD RESILER _______________________________________________ -->

        <div class="modal fade" id="addnew" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 v-if="editmode" class="modal-title" id="addnewLabel" v-show="userTypee =='Admin'">{{trans('edit_res')}}</h5>
                <h5 v-else class="modal-title" id="addnewLabel" v-show="userTypee =='Admin'">{{trans('new_res')}}</h5>

                     <h5 v-if="editmode" class="modal-title" id="addnewLabel" v-show="userTypee =='Resiler'">{{trans('edit_subres')}}</h5>
                <h5 v-else class="modal-title" id="addnewLabel" v-show="userTypee =='Resiler'">{{trans('new_subres')}}</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <form  @submit.prevent="editmode ? UpdateResiler() : CreateResiler()" enctype="multipart/form-data">

            <div class="modal-body">

                <div class="row form-group">
                        <label class="col-md-3 control-label">{{trans('username')}} :</label>

                        <div class="col-md-9">
                            <input v-if="editmode && $userType !='Admin'" type="text" name="name" id="name" class=" form-control" v-model="Resiler.name" disabled>
                            <input v-if="editmode && $userType =='Admin'" type="text" name="name" id="name" class=" form-control" v-model="Resiler.name">
                            <input v-if="!editmode" type="text" name="name" id="name" class=" form-control" v-model="Resiler.name">
                        </div>

                         <p class="error" v-if="errors.name"> {{errors.name}}</p>
                </div>

                

                <div class="row form-group"> 
                    <label class="col-md-3 control-label">{{trans('phone')}} :</label>

                    <div class="col-md-9">
                        <input type="text" name="phone" class="form-control" v-model="Resiler.phone">
                    </div>
                </div>                               

                <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('email')}} :</label>

                    <div class="col-md-9">
                        <input  type="text" name="email" class="form-control" v-model="Resiler.email" required>
                    </div>

                    <p class="error" v-if="errors.email"> {{errors.email}}</p>
                </div>
                  <div class="row form-group" v-if="userTypee =='Admin'">
                    <label class="col-md-3 control-label">{{trans('account_type')}} :</label>

                    <div class="col-md-9">
                        <select  type="text" id="type" name="type" class="form-control"  v-model="Resiler.type">

                            <option  value="Admin">{{trans('admin')}}</option>
                            <option  value="Resiler">{{trans('reseller')}}</option>
                            <option  value="subResiler">{{trans('sub_res')}}</option>
                        </select>
                    </div>

                     <p class="error" v-if="errors.type"> {{errors.type}}</p>
                </div>

                  <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('select_pack')}} :</label>

                    <div class="col-md-9">
                        <select multiple  v-model="pack" style="width: 300px;height: 127px;"> 
                        <option
                            v-for="pack in allpack"
                            :value="pack.id" :key="pack.id">
                            {{pack.package_name}}
                        </option>
                        </select>
                    </div>

                    <p class="error" v-if="errors.package_id"> {{errors.package_id}}</p>

                </div>

                

                <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('logo')}} :</label>

                    <div class="col-md-9">
                        <input type="file" id="image"  name="image"  @change="onFileSelected"> 
                    </div>
                    <p class="error" v-if="errors.image"> {{errors.image}}</p>
                </div>
                <div class="row form-group">
                        <label class="col-md-3 control-label">{{trans('host')}} :</label>
                        <div class="col-md-9" >
                            <input type="text" name="host" id="host" class=" form-control" v-model="Resiler.host">
                        </div>
                        <p class="error" v-if="errors.host"> {{errors.host}}</p>
                </div>
                <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('show_msg')}} :</label>
                    <div style="width:20px;" >
                        <input v-if="!editmode" type="checkbox" name="show_message" id="show_message" class=" form-control" :value="Resiler.show_message" v-model="Resiler.show_message" checked>
                        <input v-else type="checkbox" name="show_message" id="show_message" class=" form-control" :value="Resiler.show_message" v-model="Resiler.show_message" checked>
                    </div>
                    <p class="error" v-if="errors.show_message"> {{errors.show_message}}</p>
                </div>
                <hr class="style10">
                <br>
               
                <div class="row form-group" v-if="!editmode">
                        <label class="col-md-3 control-label">{{trans('password')}} :</label>

                        <div class="col-md-9" >
                            <input type="password" name="password" id="password" class=" form-control" v-model="Resiler.password">
                        </div>


                         <p class="error" v-if="errors.password"> {{errors.password}}</p>
                </div>

                 <div class="row form-group" v-if="editmode">
                        <label class="col-md-3 control-label"> {{trans('new_pass')}} :</label>

                        <div class="col-md-9" >
                            <input type="password" name="Newpassword" id="Newpassword" class=" form-control" v-model="Resiler.Newpassword" placeholder="lissez le champ vide si vous n'avez pas le modifie">
                        </div>


                         <p class="error" v-if="errors.password"> {{errors.password}}</p>
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


     <!--________________________________________________  Chart Modal _______________________________________________ -->

        <div class="modal" tabindex="-1" role="dialog" id="chart">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{trans('statistics')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>
                        <input type="radio" @change="onCheck(1)" value="1" class="pr-2" name="date" id="firstRadio" checked>
                        <label ><span >1 {{trans('days')}}</span> </label>
                        <input type="radio" @change="onCheck(2)" value="2" class="pr-2" name="date" >
                        <label ><span >2 {{trans('days')}}</span> </label>
                        <input type="radio" @change="onCheck(7)" value="7" class="pr-2" name="date" >
                        <label ><span >7 {{trans('days')}}</span> </label>
                    </p>
                    
                    <line-chart :chartdata="chartData" :options="options" v-if="loaded"></line-chart>

                    <div class="spinner-border" role="status" style="margin-left:50%" v-if="!loaded">
                        <span class="sr-only">{{trans('loading')}}</span>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{trans('close')}}</button>
                </div>
                </div>
            </div>
        </div>
       
    <!--________________________________________________  Modal of RECHARGE CREDIT _______________________________________________ -->


    <div class="modal fade" id="CreditRecharge" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">{{trans('rech_cred')}}</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form @submit.prevent= "updateCredit()">
                <div class="modal-body">
                    <div class="form-group" >
                        <div class="alert alert-danger" role="alert" v-if="userTypee !='Admin'">
                            <span v-if="this.$userSolde != '0' || userTypee =='Admin'">{{trans('your_crd')}} {{this.$userSolde}}</span>
                            <span v-else>{{trans('no_credit')}} </span>
                            <br>
                            <span v-if="this.$userSoldeTest != '0' || userTypee =='Admin'">{{trans('your_crd_t')}} {{this.$userSoldeTest}}</span>
                            <span v-else>{{trans('no_t_credit')}} </span> 
                            <br>
                            <span v-if="this.$userSoldeApp != '0' || userTypee =='Admin'">{{trans('your_crd_t')}} {{this.$userSoldeApp}}</span>
                            <span v-else>{{trans('no_app_credit')}} </span> 
                            <br>
                            <span v-if="this.$userGift != '0' || userTypee =='Admin'">{{trans('your_crd')}} {{this.$userGift}}</span>
                            <span v-else>{{trans('no_gift')}} </span>                        
                        </div>                           
                        <div class="row form-group"> 
                            <label class="col-md-3 control-label">{{trans('solde')}} :</label>

                            <div class="col-md-9">
                                <input v-show="this.$userSolde != '0' || userTypee =='Admin'"  type="text" min="0" class="form-control" name="solde" id="solde" v-model="sld">
                            </div>
                        </div> 

                        <div class="row form-group"> 
                            <label class="col-md-3 control-label">{{trans('bal_test')}} :</label>

                            <div class="col-md-9">
                                <input v-show="this.$userSoldeTest != '0' || userTypee =='Admin'"  type="text" min="0" class="form-control" name="soldeTest" id="soldeTest" v-model="sldTest">
                            </div>
                        </div>

                        <div class="row form-group"> 
                            <label class="col-md-3 control-label">{{trans('bal_app')}} :</label>

                            <div class="col-md-9">
                                <input v-show="this.$userSoldeApp != '0' || userTypee =='Admin'"  type="text" min="0" class="form-control" name="soldeApp" id="soldeApp" v-model="sldApp">
                            </div>
                        </div>

                        <div class="row form-group"> 
                            <label class="col-md-3 control-label">{{trans('gift')}} :</label>

                            <div class="col-md-9">
                                <input v-show="this.$userGift != '0' || userTypee =='Admin'"  type="text" min="0" class="form-control" name="soldeGift" id="soldeGift" v-model="gift">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{trans('close')}}</button>
                    <button type="submit" class="btn btn-success">{{trans('update')}}</button>
                </div>
            </form>
            </div>
        </div>
    </div>


    <!--________________________________________________  Modal of recover CREDIT _______________________________________________ -->


         <div class="modal fade" id="CreditRecover" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">{{trans('recover_crd')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form @submit.prevent= "recoverCredit()">
                    <div class="modal-body">

                        <div class="form-group" >
                            <div class="alert alert-danger" role="alert" v-if="userTypee !='Admin'">
                                <span>{{trans('your_crd')}} {{this.$userSolde}}</span>
                                <br>
                                <span>{{trans('your_crd_t')}} {{this.$userSoldeTest}}</span>
                                <br>
                                <span>{{trans('your_crd_app')}} {{this.$userSoldeApp}}</span>
                                <br>
                                <span>{{trans('your_crd_gift')}} {{this.$userGift}}</span>
                            </div>
                            
                            

                            <div class="row form-group"> 
                                <label class="col-md-3 control-label">{{trans('solde')}} :</label>

                                <div class="col-md-9">
                                    <input type="text" min="0" class="form-control" name="solde" id="solde" v-model="sld">
                                </div>
                            </div> 

                            <div class="row form-group"> 
                                <label class="col-md-3 control-label">{{trans('bal_test')}} :</label>

                                <div class="col-md-9">
                                    <input  type="text" min="0" class="form-control" name="soldeTest" id="soldeTest" v-model="sldTest">
                                </div>
                            </div>   

                            <div class="row form-group"> 
                                <label class="col-md-3 control-label">{{trans('bal_app')}} :</label>

                                <div class="col-md-9">
                                    <input  type="text" min="0" class="form-control" name="soldeApp" id="soldeApp" v-model="sldApp">
                                </div>
                            </div>   

                            <div class="row form-group"> 
                                <label class="col-md-3 control-label">{{trans('gift')}} :</label>

                                <div class="col-md-9">
                                    <input  type="text" min="0" class="form-control" name="soldegift" id="soldegift" v-model="gift">
                                </div>
                            </div>   
                            
                        </div>
                    
                        
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{trans('close')}}</button>
                        <button type="submit" class="btn btn-success">{{trans('update')}}</button>
                    </div>
                </form>
                </div>
            </div>
        </div>


    </div>

</template>

<script>


import LineChart from './LineChart.vue'
    export default {
        mounted() {
            this.getPack();
           this.ShowUsers();
                Fire.$on('AfterCreate' , () => {
                        this.ShowUsers(); 
                        this.getPack();
                        }); 
       },
        data () {
        return {
            userTypee:this.$userType,
        pack:[],
        allpack:[],
        searchQuery:'',
        editmode: false,
        sld:'',
        sldTest:'',
        sldApp:'',
        idSolde:'',
        data: new FormData(),
        users:{},
        Resiler: {
            name:'',
            password:'',
            phone:'',
            email:'',
            solde:'',
            soldeTest:'',
            soldeApp:'',
            image:'',
            type:'',
            newpassword:'',
            package_id:'',
            host:'',
            show_message:''
        },
        errors: {
            name:'',
            email:'',
            type:'',
            password:'',
            image:''
        },
        onLoad:true,
        chartData:null,
        options:null,
        loaded:false,
        dataChart:null,
        selected_user:null,
        gift:null,
        show_filter: false,
        loading_cr:false,
        loading_rec:false,
      }
    },

    components: {
        LineChart
},
        methods: {


             getPack() {
                 axios.post('code/GetPack').then(({ data }) => (this.allpack = data));
            },


              getpage(page = 1) {
                axios.post('code/resilers_all?page=' + page)
                    .then(response => {
                        this.users = response.data;
                        this.onLoad = false;
                    });
            },


            getResults(page = 1) {
                axios.post('code/resilers_all?page=' + page+'&query='+this.searchQuery)
                    .then(response => {
                        this.users = response.data;
                        this.onLoad = false;
                    });
            },
            newModel(){
                    document.getElementById("show_message").checked = true
                
                $('#addnew').modal('show');
                this.resetForm();
                this.editmode = false;
                
            },

            editModel(user){
        
                this.resetForm();
                $('#addnew').modal('show');
                this.Resiler = user;
                this.editmode = true;
                this.pack = this.Resiler.package_id.split(",");
            },

            showChart(user){

                $('#chart').modal('show');
                this.chartData = this.options = null;
                this.loaded = false;
                this.selected_user = user;
                document.getElementById("firstRadio").checked = true
                axios.post('code/getStatistic/'+user+'/1')
                .then(response => {
                    this.chartData = {
                        labels: ['Active Code', 'Users', 'Mag Device', 'Credit added to sub reseller'],
                        datasets: [
                            {
                                label: 'Data One',
                                backgroundColor: '#f87979',
                                data: response.data
                            }
                        ]
                    };

                    this.options = {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true
                                }
                            }]
                        },
                        legend: {
                            display: false
                        },
                        tooltips: {
                            callbacks: {
                            label: function(tooltipItem) {
                                    return tooltipItem.yLabel;
                            }
                            }
                        }
                    }

                   
                    this.loaded = true
                })

                
            },

            onCheck(date) {
                this.chartData = null;
                this.options = null;
                this.loaded = false;
                
                axios.post('code/getStatistic/'+this.selected_user+'/'+date)
                .then(response => {
                    this.chartData = {
                        labels: ['Active Code', 'Users', 'Mag Device', 'Credit added to sub reseller'],
                        datasets: [
                            {
                                label: 'Data One',
                                backgroundColor: '#f87979',
                                data: response.data
                            }
                        ]
                    };

                    this.options = {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            yAxes: [{
                                ticks: {
                                    beginAtZero: true
                                }
                            }]
                        },
                        legend: {
                            display: false
                        },
                        tooltips: {
                            callbacks: {
                            label: function(tooltipItem) {
                                    return tooltipItem.yLabel;
                            }
                            }
                        }
                    }

                   
                    this.loaded = true
                })
            },

            
            resetForm(){
                this.pack=[];
                this.Resiler =  {
                    id:'',
                    name:'',
                    password:'',
                    phone:'',
                    email:'',
                    solde:'',
                    soldeTest:'',
                    soldeApp:'',
                    image:'',
                    type:'',
                    Newpassword:'',
                    package_id:[],
                    host:'',
                    show_message:''
                },
                this.errors= {
                    name:'',
                    email:'',
                    type:'',
                    password:'',
                    image:''
                }
          },  

            //------------------------------------------show Users FUNCTION ----------------------------------\\

            ShowUsers (){
                axios.post('code/resilers_all').then(({ data }) => (this.users = data, this.onLoad = false));
            
            },

          //------------------------------------------CREATE FUNCTION ----------------------------------\\
            onFileSelected(e) {
               var self=this; 
                let file = e.target.files[0];
                self.Resiler.image = file;
            },

            CreateResiler() { 
                var self=this; 
                self.data.append('name',self.Resiler.name);
                self.data.append('password',self.Resiler.password);
                self.data.append('phone',self.Resiler.phone);
                self.data.append('email',self.Resiler.email);
                self.data.append('solde',self.Resiler.solde);
                self.data.append('soldeTest',self.Resiler.soldeTest);
                self.data.append('soldeApp',self.Resiler.soldeApp);
                self.data.append('type',self.Resiler.type);
                self.data.append('image',self.Resiler.image);
                self.data.append('package_id',self.pack);
                self.data.append('host',self.Resiler.host);
                self.data.append('show_message',self.Resiler.show_message);

              const headers={headers:{'Content-Type':'multipart/form-data'}};
                if(this.$userType == 'Admin' ){
                            axios.post('code/resilers',self.data,headers)
                            
                                .then(response => {
                                    Fire.$emit('AfterCreate');
                                    toast.fire({
                                        icon: 'success',
                                        title: 'Compte crée avec succès'
                                    })
                                    $('#addnew').modal('hide');
                                }).catch(error => {

                                    if (error.response.status == 422){

                                    this.errors.password = error.response.data.errors.password;
                                    this.errors.name = error.response.data.errors.name;
                                    this.errors.email = error.response.data.errors.email;
                                    this.errors.type = error.response.data.errors.type;
                                    this.errors.host = error.response.data.errors.host;
                                    this.errors.show_message = error.response.data.errors.show_message;
                                    this.errors.package_id = error.response.data.errors.package_id;
                                    this.errors.image = error.response.data.errors.image;

                                }
                                })
                    }else {
                            axios.post('code/CreateSubResiler',self.data,headers) 
                            .then(response => {
                                    Fire.$emit('AfterCreate');
                                    toast.fire({
                                        icon: 'success',
                                        title: 'Compte crée avec succès'
                                    })
                                    $('#addnew').modal('hide');
                                }).catch(error => {
                                    if (error.response.status == 422){

                                        this.errors.password = error.response.data.errors.password;
                                        this.errors.name = error.response.data.errors.name;
                                        this.errors.email = error.response.data.errors.email;
                                        this.errors.type = error.response.data.errors.type;
                                        this.errors.package_id = error.response.data.errors.package_id;
                                        this.errors.image = error.response.data.errors.image;

                                    }
                                })
                }
            
            },

          //------------------------------------------UPDATE FUNCTION ----------------------------------\\

            UpdateResiler() { 
                var self=this; 
                self.data.append('name',self.Resiler.name);
                self.data.append('Newpassword',self.Resiler.Newpassword);
                self.data.append('phone',self.Resiler.phone);
                self.data.append('email',self.Resiler.email);
                self.data.append('solde',self.Resiler.solde);
                self.data.append('soldeTest',self.Resiler.soldeTest);
                self.data.append('soldeApp',self.Resiler.soldeApp);
                self.data.append('type',self.Resiler.type);
                self.data.append('image',self.Resiler.image);
                self.data.append('host',self.Resiler.host);
                self.data.append('show_message',self.Resiler.show_message);
                self.data.append('package_id',self.pack);
                self.data.append("_method", "put");
                
                const headers={headers:{'Content-Type':'multipart/form-data'}};

                axios.post('code/resilers/'+self.Resiler.id, self.data)
                
                    .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Reseller Updated in successfully'
                        })
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        if (error.response.status == 422){

                            this.errors.password = error.response.data.errors.password;
                            this.errors.name = error.response.data.errors.name;
                            this.errors.email = error.response.data.errors.email;
                            this.errors.type = error.response.data.errors.type;
                            this.errors.package_id = error.response.data.errors.package_id;
                            this.errors.image = error.response.data.errors.image;

                        }else{
                            Swal.fire({
                                icon: 'error',
                                title: 'Oops...',
                                text: 'Something went wrong!',
                            })
                        }
                        
                        

                    })
            
            },

        //------------------------------------------DELETE FUNCTION ----------------------------------\\

            DELETEResiler(id){
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "You won't be able to revert this!",
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, delete it!'
                            }).then((result) => {
                            
                                if (result.value) {
                            
                                    axios.delete('code/resilers/'+id)
                                    .then(() => {
                                    
                                        Swal.fire(
                                        'Deleted!',
                                        'this Reseller has been deleted.',
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

     //------------------------------------------RECHARGE CREDIT FUNCTION ----------------------------------\\
    
            editSolde(user){
                $('#CreditRecharge').modal('show');
                if(this.userTypee == 'Admin') {
                    this.sld = user.solde;
                    this.sldTest = user.solde_test;
                    this.sldApp = user.solde_app;
                    this.gift = user.gift;
                }else{
                    this.sld = 0;
                    this.sldTest = 0;
                    this.sldApp = 0;
                    this.gift = 0;
                }               
                this.idSolde = user.id;
            },
            recoverSolde(user){
                $('#CreditRecover').modal('show');
                if(this.userTypee == 'Admin') {
                    this.sld = user.solde;
                    this.sldTest = user.solde_test;
                    this.sldApp = user.solde_app;
                    this.gift = user.gift;
                }else{
                    this.sld = 0;
                    this.sldTest = 0;
                    this.sldApp = 0;
                    this.gift = 0;
                }
                this.idSolde = user.id;
            },
            updateCredit() { 
                var self = this;
                if(!this.loading_cr) {
                    self.loading_cr = true;
                    axios.put('code/updateCredit/'+this.idSolde, {
                        solde : this.sld,
                        soldeTest : this.sldTest,
                        soldeApp : this.sldApp,
                        gift : this.gift
                    
                        }).then(response => {
                            Fire.$emit('AfterCreate');
                            toast.fire({
                                icon: 'success',
                                title: 'Credit recharged in successfully'
                            })
                            $('#CreditRecharge').modal('hide');
                            self.loading_cr = false;
                        }).catch(error => {
                            self.loading_cr = false;
                        })
                }
                
            
            },
            recoverCredit() { 

                var self = this;
                if(!this.loading_rec) {
                    self.loading_rec = true;
                    axios.put('code/recoverCredit/'+this.idSolde, {
                        solde : this.sld,
                        soldeTest : this.sldTest,
                        soldeApp : this.sldApp,
                        gift : this.gift
                    
                        }).then(response => {
                            Fire.$emit('AfterCreate');
                            toast.fire({
                                icon: 'success',
                                title: 'Credit recoverd in successfully'
                            })
                            $('#CreditRecover').modal('hide');
                            self.loading_rec = false;
                        }).catch(error => {
                            self.loading_rec = false;
                        })
                }
            },
            block(id) {
                axios.post('code/block/'+id, {
                    password:Math.random().toString(36).substr(2, 10)
                }).then(response => {
                    Fire.$emit('AfterCreate');
                    toast.fire({
                        icon: 'success',
                        title: 'Reseller blocked in successfully'
                    })
                }).catch(error => {
                    
                })
            },         
            unblock(id) {
                axios.post('code/unblock/'+id).then(response => {
                    Fire.$emit('AfterCreate');
                    toast.fire({
                        icon: 'success',
                        title: 'Reseller unblocked in successfully'
                    })
                }).catch(error => {
                    
                })
            }

        },

        created() {
            this.getPack();
            this.ShowUsers(); 
            Fire.$on('AfterCreate' , () => {
                    this.ShowUsers(); 
                    this.getPack();
                }); 
            
        }

        
    }
</script>
