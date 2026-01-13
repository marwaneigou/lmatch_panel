<template>

    <div class="container-fluid">
        <div class="row mb-4  justify-content-md-center">
            <div class="col-md-12">  
                <div class="card">
                    <div class="card-header">
                    <div class="row">
                     <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6">
                        <h5>{{trans('manage_host')}}</h5>
                    </div>
                         <div class="col-lg-6 col-md-6 col-sm-6 col-xs-6 d-flex justify-content-end">
                            <button class="btn btn-success"  @click="newHost()">
                                <span>{{trans('add_host')}}</span> 
                            <i class="fa fa-user-plus fa-fw"></i>
                            </button>
                         </div>
                    </div>
                      </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>{{trans('host')}}</th>
                                <th>{{trans('logo')}}</th>
                                <th>{{trans('back_img')}}</th>
                                <th>{{trans('action')}}</th>
                            </tr>
                             </thead>
                            <tbody> 
                            <tr v-for="hs in hosts.data">
                                <td>{{ hs.host }}</td>
                                <td>
                                    <img style="height: 60px;" :src="'assets/img/'+hs.logo">
                                </td>
                                <td>
                                    <img style="height: 60px;" :src="'assets/img/'+hs.background">
                                </td>
                                <td>
                                    <a href="#" @click="editHost(hs)"  title="edit">
                                        <i class="fa fa-eye blue-color"></i>
                                    </a>
                                    /
                                    <a href="#" @click="DELETEHost(hs.id)"  title="delete">
                                        <i class="fa fa-trash red-color"></i>
                                    </a>
                                </td>
                            </tr>
                        
                            </tbody>
                        </table>
                    </div>

                        <div class="card-footer">
                            <pagination :data="hosts" @pagination-change-page="getResults">
                                <span slot="prev-nav">&lt; Previous</span>
                                <span slot="next-nav">Next &gt;</span>
                            </pagination>
                        </div>

                   
                </div>
            </div>
        
        </div>

<!-- Modal {{trans('create')}} Host -->
    <div class="modal fade" id="addnew" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5  class="modal-title" id="addnewLabel">{{trans('new_host')}}</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form @submit.prevent="editmode ? UpdateHost() : CreateHost()" enctype="multipart/form-data">
                <div class="modal-body">              
                    <div class="row form-group">
                        <label class="col-md-4 control-label">{{trans('host')}} :</label>
                        <div class="col-md-8">
                            <input  type="text" name="name" class="form-control"  placeholder="host.com" v-model="host">
                            <p class="error" v-if="errors.host">{{ errors.host }}</p>
                        </div>
                    </div>  
                    <div class="row form-group">
                        <label class="col-md-4 control-label">{{trans('logo')}} :</label>
                        <div class="col-md-8">
                            <input type="file" class="form-control" @change="onLogoSelected">
                            <p class="error" v-if="errors.logo">{{ errors.logo }}</p>
                        </div>
                    </div>
                    <div class="row form-group">
                        <label class="col-md-4 control-label">{{trans('back_img')}} :</label>
                        <div class="col-md-8">
                            <input type="file" class="form-control" @change="onBackSelected">
                            <p class="error" v-if="errors.background">{{ errors.background }}</p>
                        </div>
                    </div> 
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">{{trans('close')}}</button>
                    <button  type="submit" class="btn btn-primary">{{trans('send')}}</button>
                </div>
            </form>

            </div>
        </div>
    </div>

    <!-- Medal show Host -->

     <div class="modal fade" id="showHost" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5  class="modal-title" id="addnewLabel">{{trans('host_det')}}</h5>
                
                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">              
                    <div class="row form-group">
                        <label class="col-md-4 control-label">{{trans('host')}} :</label>
                        <div class="col-md-8">
                            <p>{{host}}</p>
                        </div>
                    </div>  
                    <div class="row form-group">
                        <label class="col-md-4 control-label">{{trans('logo')}} :</label>
                        <div class="col-md-8">
                            <img style="height: 60px;" :src="'assets/img/'+logo">
                        </div>
                    </div>
                    <div class="row form-group">
                        <label class="col-md-4 control-label">{{trans('back_img')}} :</label>
                        <div class="col-md-8">
                            <img style="height: 60px;" :src="'assets/img/'+background">
                        </div>
                    </div> 
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">{{trans('close')}}</button>
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
           this.getHost(); 
           this.getUserAuth();
                Fire.$on('AfterCreate' , () => {
                        this.getHost(); 
                        this.getUserAuth();
                        }); 
       },
        data () {
        return {

            logo: '',
            background: '',
            host: '',
            host_id: '',
            hosts:{
                host:'',
                logo:'',
                background:''
            },
            errors: {
                host: "",
                logo: "",
                background: ""
            },

            user_type:'',
            user_solde:'',
            editmode: false,
        }
    },
        methods: {
            onLogoSelected(e) {
               var self=this; 
                let file = e.target.files[0];
                self.logo = file;
            },
            onBackSelected(e) {
               var self=this; 
                let file = e.target.files[0];
                self.background = file;
            },
            newHost(){
                $('#addnew').modal('show');
                this.resetForm();
                this.editmode = false;
            },
            editHost(elm){
                $('#showHost').modal('show');
                this.resetForm();
                this.logo = elm.logo;
                this.background = elm.background;
                this.host = elm.host;
                this.host_id = elm.id;
                this.editmode = true
            },
            resetForm(){
                this.logo = '';
                this.background = '';
                this.host = '';
            }, 
            CreateHost() {
                let formData = new FormData();
                formData.append('logo', this.logo);
                formData.append('background', this.background);
                formData.append('host', this.host);

                axios.post('code/add_host', formData).then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'host created in successfully'
                        })
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        if (error.response.status == 422) {
                            this.errors.logo = error.response.data.errors.logo;
                            this.errors.host = error.response.data.errors.host;
                            this.errors.background = error.response.data.errors.background;
                        }else{
                            if(error.response.data.msg) {
                                toast.fire({
                                icon: "error",
                                title: "Erreur !",
                                });
                            }                            
                        }
                    })
            
            },
            UpdateHost() {
                const headers={headers:{'Content-Type':'multipart/form-data'}};
                axios.put('code/update_host/'+this.host_id, {
                    host : this.host,
                    logo : this.logo,
                    background : this.this.background
                })
                   .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'this host Updated in successfully'
                        })
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        if (error.response.status == 422) {
                            this.errors.logo = error.response.data.errors.logo;
                            this.errors.host = error.response.data.errors.host;
                            this.errors.background = error.response.data.errors.background;
                        }else{
                            if(error.response.data.msg) {
                                toast.fire({
                                icon: "error",
                                title: "Erreur !",
                                });
                            }                            
                        }
                    })
            
            },
            getResults(page = 1) {
                axios.post('code/hosts?page=' + page)
                    .then(response => {
                        this.hosts = response.data;
                    });
            },
            getHost() {
               axios.post('code/hosts').then(({ data }) => (this.hosts = data));
            },        

            getUserAuth() {
                axios.post('code/GetUserAuth')
                    .then(response => {
                        this.user_type = response.data.user_type;
                        this.user_solde = response.data.user_solde;
                    });
            },
            DELETEHost(id){
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
                    
                            axios.post('code/delete_host/'+id)
                            .then(() => {
                            
                                Swal.fire(
                                'Deleted!',
                                'this Host has been deleted.',
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
            this.getUserAuth();
            this.getHost(); 
            Fire.$on('AfterCreate' , () => {
                    this.getHost(); 
                    this.getUserAuth();
                }); 
        
        }
    }
</script>
