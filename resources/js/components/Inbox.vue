<template>

    <div class="container-fluid">
        <div class="row mb-4  justify-content-md-center">
            <div class="col-md-12">  
                <div class="card">
                    <div class="card-header">
                    <div class="row">
                        <div class="col-12">
                            <button
                            class="btn btn-success c_btn"
                            @click="newModel()"
                            >                  
                            <i class="fa fa-plus fa-fw"></i>
                            </button>
                        </div>
                    </div>
                      </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>{{trans('subject')}}</th>
                                <th>{{trans('from')}}</th>
                                <th>{{trans('msg')}}</th>
                                <th>{{trans('action')}}</th>
                            </tr>
                             </thead>
                            <tbody> 
                            <tr v-for="msg in msgs.data">
                                <td>{{ msg.objet.substring(0,10) }}</td>
                                <td>{{ msg.name }}</td>
                                <td>{{ msg.content.substring(0,10) }}</td>
                                <td>
                                    <a href="#" @click="editModel(msg)"  title="edit">
                                        <i class="fa fa-eye blue-color"></i>
                                    </a>
                                    /
                                    <a href="#" @click="DELETEMessage(msg.id)"  title="delete">
                                        <i class="fa fa-trash red-color"></i>
                                    </a>
                                </td>
                            </tr>
                        
                            </tbody>
                        </table>
                    </div>

                        <div class="card-footer">
                            <pagination :data="msgs" @pagination-change-page="getResults">
                                <span slot="prev-nav">&lt; Previous</span>
                                <span slot="next-nav">Next &gt;</span>
                            </pagination>
                        </div>

                   
                </div>
            </div><!-- /.col -->
        
        </div><!-- /.row -->

<!-- Modal {{trans('create')}} Message -->
    <div class="modal fade" id="addnew" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5  class="modal-title" id="addnewLabel">{{trans('new_message')}}</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
         <form @submit.prevent="editmode ? UpdateMessage() : CreateMessage()" enctype="multipart/form-data">
                <div class="modal-body">

              
                     <div class="row form-group">
                        <label class="col-md-4 control-label">{{trans('subject')}} :</label>

                        <div class="col-md-8">
                            <input  type="text" name="name" class="form-control"  placeholder="Your Full Subject" v-model="msg.objet">
                        </div>

                    </div>  


                   <!-- <div class="form-group" id="content">
                            <label>Object</label>
                            <vue-editor id="editor"  name="discription" ></vue-editor>
                    </div>-->

                     <div class="row form-group">
                        <label class="col-md-4 control-label">{{trans('msg')}} :</label>

                        <div class="col-md-8">
                            <textarea class="form-control" name="notes" rows="8" placeholder="Your Message to Us" v-model="msg.content"></textarea>
                        </div>  

                    </div>   

                   <div class="row form-group" v-show="user_type == 'Admin'">
                        <label class="col-md-4 control-label">{{trans('select_res')}} :</label>

                        <div class="col-md-8">
                            <select  type="text" id="type" name="pack" class="form-control"  v-model="msg.recept_id">
                                <option :value="user.id"  v-for="user in users">{{user.name}}</option>
                                
                            </select>
                        </div>
                     </div>

                    <!-- <div class="row form-group" v-show="user_type != 'Admin'">
                        <label class="col-md-4 control-label">Select Admin :</label>

                        <div class="col-md-8">
                            <select  type="text" id="type" name="pack" class="form-control"  v-model="msg.recept_id">
                                <option :value="user.id"  v-for="user in users">{{user.name}}</option>
                                
                            </select>
                        </div>
                    </div> -->

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">{{trans('close')}}</button>

                    <button  type="submit" class="btn btn-primary">{{trans('send')}}</button>
                    

                </div>
            </form>

            </div>
        </div>
    </div>

    <!-- Medal show Message -->

     <div class="modal fade" id="showMessage" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <!-- <h5  class="modal-title" id="addnewLabel">{{trans('new_message')}}</h5> -->

                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
                <div class="modal-body">
                    <div class="card">
                        <div class="card-header" style="background-color: darksalmon;">
                            {{trans('subject')}}
                        </div>

                        <div class="card-body">
                            <div class="row form-group">

                                <div class="col-md-12">
                                <p>{{msg.objet}}</p>
                                </div>  

                             </div>
                        </div>

                        
                    </div>

                     <div class="card">
                        <div class="card-header" style="background-color: darksalmon;">
                            {{trans('msg')}}
                        </div>

                        <div class="card-body">
                            <div class="row form-group">

                                <div class="col-md-12">
                                <p>{{msg.content}}</p>
                                </div>  

                             </div>
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
           this.getMessage(); 
           this.getUserAuth();
           this.getallUsers();
                Fire.$on('AfterCreate' , () => {
                        this.getMessage(); 
                        this.getUserAuth();
                        this.getallUsers();
                        }); 
       },
        data () {
        return {

        user_type:'',
        user_solde:'',
        editmode: false,
        msgs:{},
        users:[],
         msg : {
            id:'',
            objet: '',
            content: '',
            recept_id:''
               
        }

       
      }
    },
        methods: {
            newModel(){
                $('#addnew').modal('show');
                this.resetForm();
                this.editmode = false;
            },

             editModel(msg){
        
                
                $('#showMessage').modal('show');
                this.resetForm();
                this.msg = msg;
                this.editmode = true
            },

             getallUsers() {
                axios.post('code/GetAllUsers').then(({ data }) => (this.users = data));
            },

             getResults(page = 1) {
                axios.get('code/messages?page=' + page)
                    .then(response => {
                        this.msgs = response.data;
                    });
            },

            getUserAuth() {
                axios.post('code/GetUserAuth')
                    .then(response => {
                        this.user_type = response.data.user_type;
                        this.user_solde = response.data.user_solde;
                    });
            },

            getMessage() {
               axios.get('code/messages').then(({ data }) => (this.msgs = data));
            },

            resetForm(){
                this.msg =  {
                    id:'',
                    objet: '',
                    content: '',
                    user_id:'',
                    recept_id:''
                }
            },  
             //------------------------------------------CREATE FUNCTION ----------------------------------\\

            CreateMessage() { 
   
                axios.post('code/messages', {
                    objet : this.msg.objet,
                    content: this.msg.content,
                
                    }).then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Message created in successfully'
                        })
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        
                    })
            
            },

             //------------------------------------------UPDATE FUNCTION ----------------------------------\\

            UpdateMessage() { 
                const headers={headers:{'Content-Type':'multipart/form-data'}};
                axios.put('code/messages/'+this.msg.id, this.msg)
                   .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'this Message Updated in successfully'
                        })
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        
                    })
            
            },

            //------------------------------------------DELETE FUNCTION ----------------------------------\\

            DELETEMessage(id){
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
                            
                                    axios.delete('code/messages/'+id)
                                    .then(() => {
                                    
                                        Swal.fire(
                                        'Deleted!',
                                        'this Message has been deleted.',
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
            this.getMessage(); 
            this.getallUsers();
            Fire.$on('AfterCreate' , () => {
                    this.getMessage(); 
                    this.getUserAuth();
                    this.getallUsers();
                }); 
        
        }
    }
</script>
