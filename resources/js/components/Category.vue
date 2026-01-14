
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
                        <table class="table table-hover" id="myTable">
                            <thead><tr>
                                <th>ID</th>
                                <th>{{trans('cat_name')}}</th>
                                <th>{{trans('cat_type')}}</th>
                                <th>{{trans('image')}}</th>
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
                            <tr v-for="cat in cats.data" :key="cat.id">
                                <td>{{cat.id}}</td>
                                <td>{{cat.category_name}}</td>
                                <td>{{cat.category_type}}</td>
                                <td>
                                    <img v :src="cat.category_image" style="width: 70px;height: 70px;">
                                    
                                </td>
                                <td>
                                    <a  @click="editModel(cat)" :title="trans('edit')">
                                        <i class="fa fa-pencil blue-color mr-1 mr-1" style="color: blueviolet;"></i>
                                    </a>
                                    <a @click="DELETECategory(cat.id)" :title="trans('delete')">
                                        <i class="fa fa-trash-o mr-1 mr-1" style="color: #DC143C"></i>
                                    </a>
                                </td>
                            </tr>
                        
                            </tbody>
                        </table>
                    </div>

                   <div class="card-footer">
                            <vue-paginate-al :totalPage="this.cats.last_page" activeBGColor="success" @btnClick="getpage" :withNextPrev="false"></vue-paginate-al>
                     </div>

                </div>
            </div>
        
        </div>

        

          <!-- Modal -->
        <div class="modal fade" id="addnew" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 v-if="editmode" class="modal-title" id="addnewLabel">{{trans('edit_cat')}}</h5>
                <h5 v-else class="modal-title" id="addnewLabel">{{trans('new_cat')}}</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <form @submit.prevent="editmode ? UpdateCategory() : CreateCategory()" enctype="multipart/form-data">
            <div class="modal-body">

                
                <div class="row form-group"> 
                    <label class="col-md-3 control-label">{{trans('cat_name')}} :</label>

                    <div class="col-md-7">
                        <input type="text" name="name" class="form-control" v-model="cat.category_name">
                    </div>
                    <p class="error" v-if="errors.name"> {{errors.name}}</p>
                </div>

                <div class="row form-group" style="margin-top: 23px;" > 
                    <label class="col-md-3 control-label">{{trans('image')}} :</label>

                    <div class="col-md-7">
                        <input type="text" name="image" id="image" class="form-control" v-model="cat.category_image" >
                    </div>
                </div>

                <div class="row form-group">
                    <label class="col-md-3 control-label">{{trans('select_type')}} :</label>

                    <div class="col-md-7">
                        <select type="text" id="type" name="type" class="form-control" @change="onChange()" v-model="cat.category_type">
                            <option   value="live">live</option>
                            <option   value="movie">movie</option>
                            <option   value="series">series</option>
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
           setTimeout(()=>{this.checkAdmin()},1000); 
           
           this.ShowCategory(); 
                Fire.$on('AfterCreate' , () => {
                        this.ShowCategory(); 
                        }); 
       },
        
        data () {
        return {
            searchQuery:null,
            editmode: false,
            cats:{},
            categories:{},
            data: new FormData(),
            cat : {
               id:'',
               category_name: '',
               category_image: '',
               category_type:'',
            },
            errors: {
                name:'',
                type:'',
            },
            onLoad:true,
            show_filter: false
      }
    },

                 
        
        methods: {

            getpage(page = 1) {
                axios.post('code/categories_all?page=' + page)
                    .then(response => {
                        this.cats = response.data;
                        this.onLoad = false;
                    });
            },

                    

            getResults(page = 1) {
                axios.post('code/categories_all?page=' + page+'&query='+this.searchQuery)
                    .then(response => {
                        this.cats = response.data;
                        this.onLoad = false;
                    });

            },
            newModel(){
               
                 this.resetForm();
                $('#addnew').modal('show');
                 this.cat.image ='';
                this.editmode = false;
            },

            editModel(cat){
        
                
                $('#addnew').modal('show');
                this.resetForm();
                this.cat = cat;
                this.editmode = true;
            },

            resetForm(){
                this.cat =  {
                    id:'',
                    category_name: '',
                    category_image: '',
                    category_type:'',
                   
                }
          },  


                

            //------------------------------------------show Active Code FUNCTION ----------------------------------\\

            ShowCategory (){
                axios.post('code/categories_all').then(({ data }) => (this.cats = data, this.onLoad = false));
            },
          //------------------------------------------CREATE FUNCTION ----------------------------------\\
            CreateCategory() { 
            var self=this; 
              self.data.append('name',self.cat.category_name);
              self.data.append('image',self.cat.category_image);
              self.data.append('type',self.cat.category_type);

              const headers={headers:{'Content-Type':'multipart/form-data'}};
   
                axios.post('code/categories', self.data,headers)
                
                   .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'category created in successfully'
                        })
                        this.resetForm();
                        this.data = new FormData();
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        if (error.response.status == 422){

                                this.errors.name = error.response.data.errors.name;
                                this.errors.type = error.response.data.errors.type;

                             }
                    })
            
            },

          //------------------------------------------UPDATE FUNCTION ----------------------------------\\

            UpdateCategory() { 
                var self=this; 

                self.data.append('name',self.cat.category_name);
                self.data.append('image',self.cat.category_image);
                self.data.append('type',self.cat.category_type);
                self.data.append("_method", "put");

                const headers={headers:{'Content-Type':'multipart/form-data'}};
                axios.post('code/categories/'+this.cat.id, self.data)
                   .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'category  Updated in successfully'
                        })
                        self.resetForm();
                        self.data = new FormData();
                        $('#addnew').modal('hide');
                    }).catch(error => {
                        
                    })
            
            },

        //------------------------------------------DELETE FUNCTION ----------------------------------\\

            DELETECategory(id){
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
                            
                                    axios.delete('code/categories/'+id)
                                    .then(() => {
                                    
                                        Swal.fire(
                                        'Deleted!',
                                        'this Category has been deleted.',
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
            Fire.$on('AfterCreate' , () => {
                    this.ShowCategory(); 
                }); 
        
    }
    }
</script>
