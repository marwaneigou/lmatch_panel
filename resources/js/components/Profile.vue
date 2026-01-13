<template>

    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12 ">
                <h1 class="m-0 text-dark ">{{trans('my_profile')}}</h1>
            </div>
        </div>

        <div class="row">
            <div class="col-md-12">  
                <div class="card">
                    <div class="card-header d-flex col-12 justify-content-between">
                        <h5>{{trans('manage_profile')}}</h5>
                    </div>
                    <form @submit.prevent="UpdateProfile()" enctype="multipart/form-data">
                        <div class="card-body">
                            <div class="row form-group">
                                <label class="col-md-3 control-label" >{{trans('username')}} :</label>
                                <div class="col-md-9">
                                    <input type="text" name="name" id="name" class=" form-control" v-model="profile.name" disabled>
                                </div>
                            </div>

                            <div class="row form-group"> 
                                <label class="col-md-3 control-label">{{trans('phone')}} :</label>
                                <div class="col-md-9">
                                    <input type="text" name="phone" class="form-control" v-model="profile.phone">
                                </div>
                            </div>                               

                            <div class="row form-group">
                                <label class="col-md-3 control-label">{{trans('email')}} :</label>
                                <div class="col-md-9">
                                    <input  type="text" name="email" class="form-control" v-model="profile.email">
                                </div>
                            </div>             

                            <div class="row form-group">
                                <label class="col-md-3 control-label">{{trans('logo')}} :</label>
                                <div class="col-md-9">
                                    <input type="file" id="image"  name="image" @change="onFileSelected" > 
                                </div>
                            </div>

                            <div class="row form-group">
                                <label class="col-md-3 control-label">{{trans('host')}} :</label>
                                <div class="col-md-9">
                                    <input  type="text" name="host" class="form-control" v-model="profile.host">
                                </div>
                            </div>

                            <div class="row form-group">
                                <label class="col-md-3 control-label">{{trans('sort_pack')}} :</label>
                                <div class="col-md-9">
                                    <div id="sortable">
                                        <div class="ui-state-default" v-for="p in packages" :key="p.id" :package-id="p.id"><span class="ui-icon ui-icon-arrowthick-2-n-s"></span>{{p.package_name}}</div>
                                    </div>
                                </div>
                            </div>

                            

                            <hr class="style10">
                            <br>

                            <div class="row form-group">
                                <label class="col-md-3 control-label">{{trans('new_pass')}} :</label>
                                <div class="col-md-9">
                                    <input  type="password" name="pass" id="pass" class=" form-control" v-model="profile.Newpassword" placeholder="laissez ce champs vide si vous n'allez pas changer le mot de passe">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button  type="submit" class="btn btn-success float-right" >{{trans('update')}} </button>
                        </div>
                    </form>
                
                </div>

                <div v-if="this.$userType == 'Admin'">
                    <button  type="submit" class="btn btn-info float-right mx-2" @click="users_d()">{{trans('users_dis')}} </button>
                    <button  type="submit" class="btn btn-info float-right" @click="active_d()" >{{trans('act_dis')}} </button>
                </div>

                <div class="modal" tabindex="-1" id="users" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">{{trans('users')}}</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                                <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <table class="table">
                                    <thead>
                                        <tr>
                                        <th scope="col">{{trans('username')}}</th>
                                        <th scope="col">{{trans('owner')}}</th>
                                        <th scope="col"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="code in users_disabled.data" :key="code.id">
                                            <td>{{code.username}}</td>
                                            <td>{{code.owner}}</td>
                                            <td>
                                                <a  @click="EnabledUser(code.username)" :title="trans('enable')">
                                                    <i class="fa fa-toggle-on mr-1  red-color"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div>
                                    <vue-paginate-al :totalPage="this.users_disabled.last_page" activeBGColor="success" @btnClick="getpageUser" :withNextPrev="false"></vue-paginate-al>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{trans('close')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal" tabindex="-1" id="active" role="dialog">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Active Codes</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                                <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <table class="table">
                                    <thead>
                                        <tr>
                                        <th scope="col">{{trans('username')}}</th>
                                        <th scope="col">{{trans('owner')}}</th>
                                        <th scope="col"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="code in active_disabled.data" :key="code.id">
                                            <td>{{code.number}}</td>
                                            <td>{{code.owner}}</td>
                                            <td>
                                                <a  @click="EnabledActiveCode(code.number)" :title="trans('enable')">
                                                    <i class="fa fa-toggle-on mr-1  red-color"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div>
                                    <vue-paginate-al :totalPage="this.active_disabled.last_page" activeBGColor="success" @btnClick="getpageActive" :withNextPrev="false"></vue-paginate-al>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">{{trans('close')}}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3" v-if="this.$userType == 'Admin'">
            <div class="col-md-12">  
                <div class="card">
                    <div class="card-header">
                        <h5>Tools :</h5>
                    </div>
                    <div class="card-body">
                        <div class="row form-group">
                            <label class="col-md-3 control-label" >{{trans('act_test')}} :</label>
                            <div class="col-md-9">
                                <input type="checkbox" id="testactive" name="testactive" v-model="testactive">
                            </div>
                        </div>
                        <div class="row form-group">
                            <label class="col-md-3 control-label" >{{trans('notif_msg')}} :</label>
                            <div class="col-md-9">
                                <textarea name="notification"  class="form-control" id="notification" cols="30" rows="10" v-model="notification"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button  type="submit" class="btn btn-success float-right" @click="setSettings()">{{trans('update')}} </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3" v-if="this.$userType == 'Admin'">
            <div class="col-md-12">  
                <div class="card">
                    <div class="card-header">
                        <h5>{{trans('ads')}} :</h5>
                    </div>
                    <div class="card-body">
                        <div class="row form-group">
                            <label class="col-md-3 control-label" >{{trans('select_img')}} :</label>
                            <div class="col-md-9">
                                <input type="file" @change="onAdsImgSelected">
                            </div>
                        </div>
                    </div>
                    <div class="card-body" v-show="has_ads_pic">
                        <div class="row form-group">
                            <label class="col-md-3 control-label" >{{trans('rmv_img')}} :</label>
                            <div class="col-md-9">
                                <button  type="submit" class="btn btn-danger float-left" @click="removeAds()">{{trans('remove')}} </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button  type="submit" class="btn btn-success float-right" @click="setAds()">{{trans('confirm')}} </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3" v-if="this.$userType == 'Admin'">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>VPN Settings :</h5>
                    </div>
                    <div class="card-body">
                        <!-- VPN Host 1 -->
                        <h6 class="text-primary">VPN Host 1:</h6>
                        <div class="row form-group">
                            <label class="col-md-3 control-label">Host 1 :</label>
                            <div class="col-md-9">
                                <input type="text" name="vpn_host1" class="form-control" v-model="vpn_settings.host1" placeholder="Enter VPN host 1 (e.g., vpn1.example.com)">
                            </div>
                        </div>
                        <div class="row form-group">
                            <label class="col-md-3 control-label">Protocol 1 :</label>
                            <div class="col-md-9">
                                <select name="vpn_protocol1" class="form-control" v-model="vpn_settings.protocol1">
                                    <option value="http">HTTP</option>
                                    <option value="https">HTTPS</option>
                                </select>
                            </div>
                        </div>
                        <div class="row form-group">
                            <label class="col-md-3 control-label">Port 1 :</label>
                            <div class="col-md-9">
                                <input type="text" name="vpn_port1" class="form-control" v-model="vpn_settings.port1" placeholder="Enter VPN port 1 (e.g., 80 for HTTP, 443 for HTTPS)">
                            </div>
                        </div>

                        <hr>

                        <!-- VPN Host 2 -->
                        <h6 class="text-primary">VPN Host 2:</h6>
                        <div class="row form-group">
                            <label class="col-md-3 control-label">Host 2 :</label>
                            <div class="col-md-9">
                                <input type="text" name="vpn_host2" class="form-control" v-model="vpn_settings.host2" placeholder="Enter VPN host 2 (e.g., vpn2.example.com)">
                            </div>
                        </div>
                        <div class="row form-group">
                            <label class="col-md-3 control-label">Protocol 2 :</label>
                            <div class="col-md-9">
                                <select name="vpn_protocol2" class="form-control" v-model="vpn_settings.protocol2">
                                    <option value="http">HTTP</option>
                                    <option value="https">HTTPS</option>
                                </select>
                            </div>
                        </div>
                        <div class="row form-group">
                            <label class="col-md-3 control-label">Port 2 :</label>
                            <div class="col-md-9">
                                <input type="text" name="vpn_port2" class="form-control" v-model="vpn_settings.port2" placeholder="Enter VPN port 2 (e.g., 80 for HTTP, 443 for HTTPS)">
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <button type="button" class="btn btn-success float-right" @click="updateVpnSettings()">Update VPN Settings</button>
                    </div>
                </div>
            </div>
        </div>

    </div>

</template>

<script>
    export default {
        mounted() {
            this.sort();
           this.ShowUser();
           this.getUserAuth();
           this.showNotifs();
           this.getSettings();
           this.getVpnSettings();
                Fire.$on('AfterCreate' , () => {
                        this.ShowUser(); 
                        this.getUserAuth();
                        this.showNotifs();
                        }); 
       },
        data () {
        return {
        notif:'',
        NotifArray:[],
        
        user_type:'',
        user_solde:'',
        user_id:'',
        editmode: false,
        sld:'',
        idSolde:'',
        data: new FormData(),
        users:{},

        profile: {
            name:'',
            phone:'',
            email:'',
            solde:'',
            image:'',
            type:'',
            host:'',
            Newpassword:''

        },
        users_disabled:[],
        active_disabled:[],
        packages:[],
        notification:'',
        testactive:true,
        ads_pic: '',
        has_ads_pic: false,
        vpn_settings: {
            host1: '',
            protocol1: 'http',
            port1: '80',
            host2: '',
            protocol2: 'http',
            port2: '80'
        }
      }
    },
        methods: {

            sort() {
                let themejs3 = document.createElement('link')
                themejs3.setAttribute('href', 'https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css')
                themejs3.setAttribute('rel', 'stylesheet')
                document.head.appendChild(themejs3)

                let themejs2 = document.createElement('script')
                themejs2.setAttribute('src', 'https://code.jquery.com/ui/1.12.1/jquery-ui.js')
                document.head.appendChild(themejs2)
                setTimeout(() => {
                    $( function() {
                        $( "#sortable" ).sortable();
                        $( "#sortable" ).disableSelection();
                    } );
                }, 4000);
                
                
            },
            showNotifs(){
                axios.post('code/notifications_all').then(({ data }) => (this.NotifArray = data));
            },

            getSettings(){
                axios.post('/settings').then(({ data }) => (this.notification = data[0].bande, this.testactive = data[0].test_active, this.has_ads_pic = data[0].ads == '' ? false :  true));
            },

            setSettings(){
                axios.post('/settings/update', {
                    notification : this.notification,
                    testactive : this.testactive                
                }).then(response => {
                    toast.fire({
                        icon: 'success',
                        title: 'Success'
                    })
                }).catch(error => {
                    toast.fire({
                        icon: 'error',
                        title: 'Erreur'
                    }) 
                })
            },

            setAds(){
                let ads_data = new FormData();
                ads_data.append('image', this.ads_pic);
                axios.post('/settings/ads', ads_data).then(response => {
                    this.getSettings();
                    toast.fire({
                        icon: 'success',
                        title: 'Success'
                    })
                }).catch(error => {
                    toast.fire({
                        icon: 'error',
                        title: 'Erreur'
                    }) 
                })
            },

            removeAds(){
                axios.post('/settings/removeAds', []).then(response => {
                    this.getSettings();
                    toast.fire({
                        icon: 'success',
                        title: 'Success'
                    })
                }).catch(error => {
                    toast.fire({
                        icon: 'error',
                        title: 'Erreur'
                    }) 
                })
            },

            users_d(){
               
                $('#users').modal('show');

                this.getUsersD();
            },

            active_d(){
               
                $('#active').modal('show');
                this.getActiveD();
            },
            addNotif(){
                 axios.post('code/notifications', {
                    notif : this.notif
                    
                
                    }).then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Notification created in successfully'
                        })
                    }).catch(error => {
                            
                    })

                    this.notif = '';
            
               
            },

            
            DELETENotif(id){
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
                            
                                    axios.delete('code/notifications/'+id)
                                    .then(() => {
                                    
                                        Swal.fire(
                                        'Deleted!',
                                        'this Notification has been deleted.',
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
            getUserAuth() {
                axios.post('code/GetUserAuth')
                    .then(response => {
                        this.profile.user_id = response.data.user_id;
                        this.profile.type = response.data.user_type;
                        this.profile.solde = response.data.user_solde;
                        this.profile.name = response.data.name;
                        this.profile.host = response.data.host;
                        this.profile.phone = response.data.phone;
                        this.profile.email = response.data.email;
                        this.packages = response.data.packages;
                    });
            },

            getUsersD() {
                axios.post('code/getUsersD')
                    .then(response => {
                        this.users_disabled = response.data.users;
                    });
            },
            getpageUser(page = 1) {
                axios.post('code/getUsersD?page=' + page)
                    .then(response => {
                        this.users_disabled = response.data.users;
                    });
            },
            getActiveD() {
                axios.post('code/getActiveD')
                    .then(response => {
                        this.active_disabled = response.data.active;
                    });
            },
            getpageActive(page = 1) {
                axios.post('code/getActiveD?page=' + page)
                    .then(response => {
                        this.active_disabled = response.data.active;
                    });
            },

            EnabledUser(number) {
                axios.post('code/UsersD/'+number)
                    .then(response => {
                        this.users_disabled = response.data.users;
                        this.getUsersD();
                    });
                    
            },
            EnabledActiveCode(number) {
                axios.post('code/ActiveD/'+number)
                    .then(response => {
                        this.active_disabled = response.data.active;
                        this.getActiveD();
                    });
            },
            
            resetForm(){
                this.profile =  {
                    id:'',
                    name:'',
                    password:'',
                    phone:'',
                    email:'',
                    solde:'',
                    image:'',
                    type:'',
                    host:'',
                }
          },  

            //------------------------------------------show Users FUNCTION ----------------------------------\\

            ShowUser (){
                axios.post('code/resilers_all').then(({ data }) => (this.users = data));
            
            },

          //------------------------------------------CREATE FUNCTION ----------------------------------\\
            onFileSelected(e) {
               var self=this; 
                let file = e.target.files[0];
                self.profile.image = file;
            },

            onAdsImgSelected(e) {
               var self=this; 
                let file = e.target.files[0];
                self.ads_pic = file;
            },
            

          //------------------------------------------UPDATE FUNCTION ----------------------------------\\

            UpdateProfile() { 
                var self=this; 
                let listP = document.getElementsByClassName('ui-state-default');
                let list_package = [];
                for (let i = 0; i < listP.length; i++) {
                    const element = listP[i];
                    list_package.push(element.getAttribute("package-id"));
                }
                self.data.append('name',self.profile.name);
                self.data.append('Newpassword',self.profile.Newpassword);
                self.data.append('phone',self.profile.phone);
                self.data.append('email',self.profile.email);
                self.data.append('image',self.profile.image);
                self.data.append('host',self.profile.host);
                self.data.append('packages',list_package);
                self.data.append("_method", "put");
                
                const headers={headers:{'Content-Type':'multipart/form-data'}};

                axios.post('code/updateProfile', self.data)
                
                    .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Your Profile Updated in successfully'
                        })
                    }).catch(error => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Oops...',
                            text: 'Something went wrong!',
                            footer: '<a href>Why do I have this issue?</a>'
                        })
                    })
            
            },

            getVpnSettings() {
                axios.get('code/vpn/settings')
                    .then(response => {
                        if (response.data) {
                            // Load host 1 settings
                            if (response.data.host1) {
                                this.vpn_settings.host1 = response.data.host1.host || '';
                                this.vpn_settings.protocol1 = response.data.host1.protocol || 'http';
                                this.vpn_settings.port1 = response.data.host1.port || '80';
                            }

                            // Load host 2 settings
                            if (response.data.host2) {
                                this.vpn_settings.host2 = response.data.host2.host || '';
                                this.vpn_settings.protocol2 = response.data.host2.protocol || 'http';
                                this.vpn_settings.port2 = response.data.host2.port || '80';
                            }
                        }
                    })
                    .catch(error => {
                        console.log('No VPN settings found or error loading settings');
                    });
            },

            updateVpnSettings() {
                // Validate host 1
                if (!this.vpn_settings.host1.trim()) {
                    toast.fire({
                        icon: 'error',
                        title: 'Please enter VPN Host 1'
                    });
                    return;
                }

                if (!this.vpn_settings.port1.trim()) {
                    toast.fire({
                        icon: 'error',
                        title: 'Please enter VPN Port 1'
                    });
                    return;
                }

                // Validate host 2
                if (!this.vpn_settings.host2.trim()) {
                    toast.fire({
                        icon: 'error',
                        title: 'Please enter VPN Host 2'
                    });
                    return;
                }

                if (!this.vpn_settings.port2.trim()) {
                    toast.fire({
                        icon: 'error',
                        title: 'Please enter VPN Port 2'
                    });
                    return;
                }

                axios.post('code/vpn/settings', {
                    host1: this.vpn_settings.host1,
                    protocol1: this.vpn_settings.protocol1,
                    port1: this.vpn_settings.port1,
                    host2: this.vpn_settings.host2,
                    protocol2: this.vpn_settings.protocol2,
                    port2: this.vpn_settings.port2
                })
                .then(response => {
                    toast.fire({
                        icon: 'success',
                        title: 'VPN settings updated successfully'
                    });
                })
                .catch(error => {
                    toast.fire({
                        icon: 'error',
                        title: 'Failed to update VPN settings'
                    });
                });
            }

        },

        created() {

        this.getUserAuth();
        this.ShowUser(); 
        this.showNotifs();
        Fire.$on('AfterCreate' , () => {
                this.ShowUser(); 
                this.getUserAuth();
                this.showNotifs();
             }); 
        
    }
    }
</script>

