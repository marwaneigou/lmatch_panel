<style>
    .error {
        color: red;
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
                            <button
                            v-show="this.$userSolde != '0' || this.$userSoldeTest != '0'"
                            class="btn btn-success c_btn"
                            @click="newModel()"
                            >                  
                            <i class="fa fa-plus fa-fw"></i>
                            </button>
                        </div>
                        <div class="container col-12 glass-table-container p-3 mt-2" v-if="show_filter">
                            <div class="row">
                                <div class="input-group col-12 mb-2">
                                    <input                  
                                    type="text"
                                    class="form-control"
                                    name="search"
                                    v-model="searchQuery"
                                    v-bind:placeholder="trans('search')"
                                    />
                                    <div class="input-group-append" @click="getResults()">
                                    <span class="input-group-text bg-success border-0 text-white"><i class="fa fa-search"></i></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover">
                            <thead><tr>
                                <th>{{trans('category')}}</th>
                                <th>{{trans('ch_name')}}</th>
                                <th>{{trans('image')}}</th>
                                <th>{{trans('type')}}</th>
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
                            <tr v-for="channel in channels.data" :key="channel">
                                <td>{{channel.CategoryName}}</td>
                                <td>{{channel.stream_display_name}}</td>
                                <td>                                   
                                    <img :src="channel.stream_icon" style="width: 70px;height: 70px;">

                                </td>

                               

                               <td>
                                    <span v-if="channel.type == '1'">live</Span>
                                    <span v-else-if="channel.type == '2'">Movie</Span>
                                    <span v-else>serie</Span>
                                </td>
                                <td>
                                    <a  @click="editModel(channel)"  :title="trans('edit')">
                                        <i class="fa fa-pencil blue-color mr-1 mr-1" style="color: blueviolet;"></i>
                                    </a>
                                    <a  @click="DELETEChannel(channel.id)"  :title="trans('delete')">
                                        <i class="fa fa-trash-o mr-1 mr-1" style="color: #DC143C"></i>
                                    </a>
                                </td>
                            </tr>
                        
                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer">
                       
                        <vue-paginate-al :totalPage="this.channels.last_page" activeBGColor="success" @btnClick="getpage" :withNextPrev="false"></vue-paginate-al>
                        
                    </div>
                      

                </div>
            </div>
        
        </div>

        

          <!-- Modal -->
        <div class="modal fade" id="addnew" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 v-if="editmode" class="modal-title" id="addnewLabel">{{trans('edit_ch')}}</h5>
                <h5 v-else class="modal-title" id="addnewLabel">{{trans('new_ch')}}</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <form @submit.prevent="editmode ? UpdateChannel() : CreateChannel()" enctype="multipart/form-data">
            <div class="modal-body">

               

                 <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('select_cat')}} :</label>

                    <div class="col-md-9">
                         <select v-model="channel.category_id"   class="form-control">
                                <option  :value="cat.id"  v-for="cat in cats" :key="cat.id">
                                    <span >{{cat.category_name}}</span>
                                </option>
                        </select>
                    </div>

                     <p class="error" v-if="errors.category_id"> {{errors.category_id}}</p>
                </div>

              
                <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('ch_title')}} :</label>

                    <div class="col-md-9">
                        <input  type="text" name="title" class="form-control"  v-model="channel.stream_display_name">
                    </div>
                <p class="error" v-if="errors.stream_display_name"> {{errors.stream_display_name}}</p>
                </div>

                <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('str_src')}} :</label>

                    <div class="col-md-9">
                         <input type="text" name="url" class="form-control"  v-model="channel.stream_source">
                    </div>
                </div>


                 <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('str_icon')}} :</label>

                    <div class="col-md-9">
                        <input type="text" id="lien" name="icon" class="form-control"  v-model="channel.stream_icon">
                    </div>
                </div>

                 <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('select_type')}} :</label>

                    <div class="col-md-7">
                        <select type="text" id="type" name="type" class="form-control" @change="onChange()" v-model="channel.type">
                            <option   value="1">live</option>
                            <option   value="2">movie</option>
                            <option   value="3">series</option>
                        </select>
                    </div> 
                     <p class="error" v-if="errors.type"> {{errors.type}}</p>
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
       
    </div>

</template>

<script>
    export default {
        mounted() {
           this.ShowCategory();
           this.ShowChannel(); 
                Fire.$on('AfterCreate' , () => {
                        this.ShowChannel(); 
                        }); 
       },
        data () {
        return {
            searchQuery:null,
            editmode: false,
            channels:{},
            data: new FormData(),
            islien: false,
            isupload: false,
            cats:[],
            channel : {
                id:'',
                stream_display_name: '',
                category_id: '',
                stream_icon:'',
                stream_source:'', 
                type:'', 
            },

            errors: {
                stream_display_name:'',
                category_id:'',
                type:'',
            },
            onLoad:true,
            show_filter: false
      }
    },
        methods: {

            getpage(page = 1) {
                axios.post('code/channels_all?page=' + page)
                    .then(response => {
                        this.channels = response.data;
                        this.onLoad = false;
                    });
                    console.log(page);
            },
            ShowCategory (){
                axios.post('code/Getcategories').then(({ data }) => (this.cats = data));
            },

            getResults(page = 1) {
                axios.post('code/channels_all?page=' + page+'&query='+this.searchQuery)
                    .then(response => {
                        this.channels = response.data;
                        this.onLoad = false;
                    });
            },
            newModel(){
               
                $('#addnew').modal('show');
                 this.resetForm();
                this.editmode = false;
            },

            editModel(channel){
        
                
                $('#addnew').modal('show');
                this.resetForm();
                this.channel = channel;
                this.editmode = true
                
            },

            resetForm(){
                this.channel =  {
                    id:'',
                    stream_display_name: '',
                    category_id: '',
                    icon:'',
                    stream_source:'', 
                    type:'', 
                }
          },  

       

            //------------------------------------------show Active Code FUNCTION ----------------------------------\\

            ShowChannel(){
                axios.post('code/channels_all').then(({ data }) => (this.channels = data, this.onLoad = false));
            
            },
          //------------------------------------------CREATE FUNCTION ----------------------------------\\

           
            CreateChannel() { 
            var self=this; 
                self.data.append('title',self.channel.stream_display_name);
                self.data.append('category_id',self.channel.category_id);
                self.data.append('icon',self.channel.stream_icon);
                self.data.append('url',self.channel.stream_source);
                self.data.append('type',self.channel.type);


              const headers={headers:{'Content-Type':'multipart/form-data'}};
   
                axios.post('code/channels', self.data,headers)
                
                   .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Channel created in successfully'
                        })
                        this.resetForm();
                        this.data = new FormData();
                       
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        if (error.response.status == 422){

                                this.errors.stream_display_name = error.response.data.errors.stream_display_name;
                                this.errors.type = error.response.data.errors.type;
                                this.errors.category_id = error.response.data.errors.category_id;

                             }
                    console.log(error)
                    })
            
            },

          //------------------------------------------UPDATE FUNCTION ----------------------------------\\

            UpdateChannel() { 
                var self=this; 
                self.data.append('title',self.channel.stream_display_name);
                self.data.append('category_id',self.channel.category_id);
                self.data.append('icon',self.channel.icon);
                self.data.append('url',self.channel.stream_source);
                self.data.append('type',self.channel.type);
                self.data.append("_method", "put");
                const headers={headers:{'Content-Type':'multipart/form-data'}};

                axios.post('code/channels/'+self.channel.id, self.data)
                   .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'channel Updated in successfully'
                        })
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        
                    console.log(error)
                    })
            
            },

        //------------------------------------------DELETE FUNCTION ----------------------------------\\

            DELETEChannel(id){
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
                            
                                    axios.delete('code/channels/'+id)
                                    .then(() => {
                                    
                                        Swal.fire(
                                        'Deleted!',
                                        'this Channel has been deleted.',
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

                this.ShowCategory();
                this.ShowChannel(); 
                Fire.$on('AfterCreate' , () => {
                    this.ShowChannel(); 
                }); 
                
    }
    }
</script>
