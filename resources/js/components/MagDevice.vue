<style>
    .error {
        color: #DC143C;
    }
    .swal2-title{
        font-size: 1.125em !important;
    }

</style>

<template>

    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-md-12">  
                 <div class="alert alert-danger" role="alert" v-if="this.$userSolde == '0' && this.$userSoldeTest == '0'">
                    <span>{{trans('no_balance2')}} </span>
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
                        <div class="container col-12 glass-table-container p-3 mt-2" v-if="show_filter">
                            <div class="row">
                                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 col-12 mb-2">
                                    <select class="form-control" v-model="filterVal" @change="filter()">
                                    <option value="0" selected>{{trans('all_items')}}</option>
                                    <option value="1">{{trans('expired')}}</option>
                                    <option value="2">{{trans('online')}}</option>
                                    <option value="3">{{trans('alm_expired')}}</option>
                                    <option value="4">{{trans('trial')}}</option>
                                    <option value="5">{{trans('disabled')}}</option>
                                    <option value="6">{{trans('enabled')}}</option>                  
                                    </select>
                                </div>
                                <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 col-12 mb-2">
                                    <select class="form-control" v-model="byUser" @change="showResDet()">
                                    <option value="">{{trans('all')}}</option>
                                    <option v-if="res.user" v-for="res in resllers" v-bind:key="res.user_id" :value="res.user_id">{{res.user ? res.user : ''}}</option>
                                    </select>
                                </div>
                                <div class="input-group col-lg-4 col-md-4 col-sm-12 col-xs-12 col-12 mb-2">
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
                        <!-- <div class="col-lg-3 col-md-6 col-sm-12 col-xs-4 mb-2 d-flex justify-content-end">
                            <button
                            v-show="this.$userSolde != '0' || this.$userSoldeTest != '0'"
                            class="btn btn-success"
                            @click="newModel()"
                            >
                            {{trans('new_code')}}
                            <i class="fa fa-user-plus fa-fw"></i>
                            </button>
                        </div> -->
                    </div>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{trans('online')}}</th>
                                    <th>{{trans('owner')}}</th>
                                    <th>{{trans('name')}}</th>
                                    <th>{{trans('mac_addr')}}</th>
                                    <th>{{trans('rem_time')}}</th>
                                    <th>{{trans('days')}}</th>
                                    <th>{{trans('status')}}</th>
                                    <th>{{trans('last_con')}}</th>
                                    <th>{{trans('last_seen_ch')}}</th>
                                    <th>{{trans('latency')}}</th>
                                    <th>{{trans('action')}}</th>
                                </tr>
                            </thead>
                            <tr>
                                <td colspan="11" class="text-center" v-if="onLoad">
                                    <div class="spinner-border" role="status">
                                        <span class="sr-only">{{trans('loading')}}</span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-for="code in codes.data" :key="code.id">
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-if='code.online == 0'><img src="/assets/img/offline.png" style="width: 27px;" alt="Offline"></td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-else><img src="/assets/img/online.png" style="width: 27px;" alt="Online"></td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.user ? code.user.name : '' }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.name }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">{{code.mac }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">
                                    <span v-if="code.time !== '' && today <= code.time">{{code.time }}</span>
                                    <span v-else-if="code.time !== ''&& today > code.time" style="color: #DC143C;">{{trans('expired')}}</span>
                                </td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-if="$userType !='Admin'">{{code.days }}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-else><input type="text" class="form-control" @keyup="changeDays($event, code.mac)" :value="code.days"></td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]">
                                    <span v-if="code.time !== '' && today <= code.time" style="color: #2FA360;" >{{trans('activated')}}</span>
                                    <span v-else-if="code.time !== '' && today > code.time"  style="color: #DC143C;">{{trans('expired')}}</span>
                                    <span v-else  style="color: #DC143C;">{{trans('not_activ')}}</span>
                                </td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-if="code.flag != ''" class="text-center">   
                                    <img :src="getImgUrl(code.flag)" alt=""><br>
                                    {{code.user_ip}}
                                </td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-else class="text-center">{{code.user_ip}}</td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" class="text-center">{{code.stream_name}} <br> <span v-if="code.last_seen_date">[ {{ code.last_seen_date}} ] </span></td>
                                <td v-if="code.online == 1">
                                    <star-rating :rating="code.latency" :star-style="starStyle" :isIndicatorActive="false"></star-rating>
                                </td>
                                <td v-else> - </td>
                                <td v-if="code.enabled == 1">
                                    <a v-if="code.has_is_trial" @click="editModel(code)" :title="trans('edit')">
                                        <i class="fa fa-pencil blue-color mr-1" style="color: blueviolet;"></i>
                                    </a>
                                   
                                    
                                    <a @click="ResetMac(code.id)" :title="trans('reset')">
                                        <i class="fa fa-repeat" style="color: blueviolet;"></i>
                                    </a>
                                    <a  @click="showModalDownload(code.mac)"  :title="trans('download')">
                                        <i class="fa fa-arrow-circle-o-down mr-1" style="color: blueviolet;"></i>
                                    </a>
                                      
                                    <a  v-show="$route.meta[0].content != '0'" @click="renewModal(code.mac, code.package_id)"  :title="trans('renew')">
                                        <i class="fa fa-refresh mr-1" style="color: blueviolet"></i>
                                    </a>
                                    <a
                                        v-if="(code.time !== '' && today <= code.time) || (code.time !== '' && today > code.time)"
                                        @click="DELETEActiveCode(code.id, 'transfer')"
                                        :title="trans('tr_to_user')"
                                    >
                                        <i class="fa fa-arrow-right mr-1 mr-1" style="color: blueviolet"></i>
                                    </a>
                                     
                                    <a  @click="DELETEActiveCode(code.id, 'disabled')" :title="trans('disable')">
                                        <i class="fa fa-toggle-on mr-1" style="color: green;"></i>
                                    </a>

                                    <a  v-show="$userType == 'Admin' || code.is_trial == '1'" @click="DELETEActiveCode(code.id, 'delete')" :title="trans('delete')">
                                        <i class="fa fa-trash-o mr-1" style="color:#DC143C;"></i>
                                    </a>
                                   
                                </td>
                                <td :style="[code.enabled == 0 ? {'background-color':'#dee2e6'} : '' ]" v-else>
                                    <a  @click="EnabledMagDevice(code.id)" :title="trans('enable')">
                                        <i class="fa fa-toggle-on mr-1 red-color"></i>
                                    </a>
                                </td>
                            </tr>
                        
                        </table>
                    </div><!-- /.card-body -->

                   <div class="card-footer">
                       
                           <vue-paginate-al :totalPage="this.codes.last_page" activeBGColor="success" @btnClick="getResults" :withNextPrev="false"></vue-paginate-al>
                     </div>

                </div>
            </div><!-- /.col -->
        
        </div><!-- /.row -->

        

          <!-- Modal -->
        <div class="modal fade" id="addnew" tabindex="-1" role="dialog" aria-labelledby="addnewLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 v-if="editmode" class="modal-title" id="addnewLabel">{{trans('edit_mag')}}</h5>
                <h5 v-else class="modal-title" id="addnewLabel">{{trans('new_mag')}}</h5>

                <button type="button" class="close" data-dismiss="modal" aria-label="close">
                <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <form @submit.prevent="editmode ? UpdateActiveCode() : CreateActiveCode()" enctype="multipart/form-data">
            <div class="modal-body">

                

                 <div class="row form-group">
                    <label class="col-md-12 control-label form_label">{{trans('select_pack')}} :</label>

                    <div class="col-md-12" v-if="code.is_trial == 0 || !editmode || this.$userType == 'Admin'">
                        <select  type="text" id="pack" name="pack" class="form-control" @change="onChange()" v-model="code.package_id" v-if="this.$userType != 'Admin'">
                            <option  :value="pack.id" v-for="pack in packs" :key="pack.id" v-if="test_active == 1">{{pack.package_name}}</option>
                            <option  :value="pack.id" v-for="pack in packs" :key="pack.id" v-if="test_active == 0  && pack.is_trial == '0'">{{pack.package_name}}</option>
                        </select>
                        <select  type="text" id="pack" name="pack" class="form-control" @change="onChange()" v-model="code.package_id" v-if="this.$userType == 'Admin'">
                            <option  :value="pack.id" v-for="pack in packs" :key="pack.id">{{pack.package_name}}</option>                     
                        </select>
                    </div>
                    <div class="col-md-12" v-else>
                        <span class="p-2 bg-gray rounded" v-if="pack.package_name">{{ pack.package_name }}</span>
                    </div>
                    <input style="margin-left: 15px;margin-top: 15px;" v-if="pack.all_bouquets" type="checkbox" name="select_All" @change="selectAll($event, pack)" class="pr-2"> <label style="margin-left: 5px;margin-top: 10px;" v-if="pack.all_bouquets" class="form_label" for="select_All">{{trans('select_all')}}</label>
                    <h3 v-if="pack.all_bouquets" style="display: block;width: 100%;background: rgb(103, 58, 183);color: white;padding: 5px 15px;border-radius: 20px;margin: 15px 5px;">Live</h3>
                    <draggable delay="60" delay-on-touch-only v-model="pack.all_bouquets" draggable=".item" class="bq_items">
                        <div v-for="(bouquet, index) in pack.all_bouquets" :key="index" class="item" v-if="pack.live.includes(bouquet)">
                            <input
                            v-if="!editmode"
                            type="checkbox"
                            @change="onCheck(bouquet, pack)"
                            :id="'bouquet_' + bouquet"
                            :value="bouquet"
                            class="pr-2"
                            :name="'bouquet_' + bouquet"
                            checked
                            />
                            <input
                            v-else
                            type="checkbox"
                            @change="onCheck(bouquet, pack)"
                            :id="'bouquet_' + bouquet"
                            :value="bouquet"
                            class="pr-2"
                            :name="'bouquet_' + bouquet"
                            :checked="code.selected_bouquets.includes(bouquet)"
                            />
                            <!-- <input
                            v-else
                            type="checkbox"
                            @change="onCheck(bouquet, pack)"
                            :id="'bouquet_' + bouquet"
                            :value="bouquet"
                            class="pr-2"
                            :name="'bouquet_' + bouquet"
                            :checked="code.selected_bouquets.includes(bouquet)"
                            /> -->
                            <i class="fa fa-arrows" style="float:right;"></i>
                            <label :for="'bouquet_' + bouquet">{{ getBouquetName(bouquet, pack.names) }}</label>
                        </div>
                    </draggable>
                    <h3 v-if="pack.all_bouquets" style="display: block;width: 100%;background: rgb(103, 58, 183);color: white;padding: 5px 15px;border-radius: 20px;margin: 15px 5px;">Movies / Series</h3>
                    <draggable delay="60" delay-on-touch-only v-model="pack.all_bouquets" draggable=".item" class="bq_items">
                        <div v-for="(bouquet, index) in pack.all_bouquets" :key="index" class="item" v-if="pack.live.includes(bouquet) == false">
                            <input
                            v-if="!editmode"
                            type="checkbox"
                            @change="onCheck(bouquet, pack)"
                            :id="'bouquet_' + bouquet"
                            :value="bouquet"
                            class="pr-2"
                            :name="'bouquet_' + bouquet"
                            checked
                            />
                            <input
                            v-else
                            type="checkbox"
                            @change="onCheck(bouquet, pack)"
                            :id="'bouquet_' + bouquet"
                            :value="bouquet"
                            class="pr-2"
                            :name="'bouquet_' + bouquet"
                            :checked="code.selected_bouquets.includes(bouquet)"
                            />
                            <!-- <input
                            v-else
                            type="checkbox"
                            @change="onCheck(bouquet, pack)"
                            :id="'bouquet_' + bouquet"
                            :value="bouquet"
                            class="pr-2"
                            :checked="code.selected_bouquets.includes(bouquet)"
                            /> -->
                            <i class="fa fa-arrows" style="float:right;"></i>
                            <label :for="'bouquet_' + bouquet">{{ getBouquetName(bouquet, pack.names) }}</label>
                        </div>
                    </draggable>
                    <!-- <ul style=" columns: 3; -webkit-columns: 3;-moz-columns: 3;width:100%;list-style:none;margin-top:10px;">
                        <li v-for="bouquet in pack.all_bouquets" :key="bouquet" style="">
                            <input v-if="!editmode" type="checkbox" @change="onCheck(bouquet, pack)" :id="'bouquet_'+bouquet" :value="bouquet" class="pr-2" checked>
                            <input v-else type="checkbox" @change="onCheck(bouquet, pack)" :id="'bouquet_'+bouquet" :value="bouquet" class="pr-2" :checked="code.selected_bouquets.includes(bouquet)">
                            <label v-for="name in pack.names" :key="name.id" ><span v-if="name.id == bouquet">{{name.bouquet_name}}</span> </label>
                        </li>
                    </ul> -->
                    <p class="error" v-if="errors.pack"> {{errors.pack}}</p>
                </div>

              
                <div class="row form-group">
                    <label class="col-md-12 control-label form_label">{{trans('cl_name')}} :</label>

                    <div class="col-md-12">
                        <input  type="text" name="name" class="form-control" v-model="code.name">
                    </div>

               <p class="error" v-if="errors.name"> {{errors.name}}</p>
                </div>

             

                <div class="row form-group">
                    <label class="col-md-12 control-label form_label">MAC :</label>

                    <div class="col-md-12">
                        <input type="text" name="mac" class="form-control" @keyup="uperCaseMAC()" v-model="code.mac">
                    </div> 

                     <p class="error" v-if="errors.mac"> {{errors.mac}}</p>
                </div>

                  <div class="row form-group">
                    <label class="col-md-12 control-label form_label">{{trans('notes')}} :</label>

                    <div class="col-md-12">
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

        <div class="modal fade" id="cpCode" tabindex="-1" role="dialog" aria-labelledby="cpCodeLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cpCodeLabel">{{trans('arc_code_coupon')}}</h5>

                    <button type="button" class="close" data-dismiss="modal" aria-label="close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body text-center">
                    <p>{{cp_code}}</p>
                    <button type="button" @click="copyCode(cp_code)" class="btn btn-primary">{{trans('copy')}}</button>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">{{trans('close')}}</button>
                </div>

                </div>
            </div>
        </div>


        <div class="modal" tabindex="-1" id="renewModal" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                <div class="modal-header bg-success">
                    <h5 class="modal-title">{{trans('renew')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <ul style="list-style: none;">
                        <li><input type="radio" name="months" value="30" class="mr-1" v-model="monthRenew">1 month (0.1 point)</li>
                        <li><input type="radio" name="months" value="90" class="mr-1" v-model="monthRenew">3 months (0.3 point)</li>
                        <li><input type="radio" name="months" value="180" class="mr-1" v-model="monthRenew">6 months (0.6 point)</li>
                        <li><input type="radio" name="months" value="365" class="mr-1" v-model="monthRenew">1 year (1 point)</li>
                    </ul>
                </div>
                <div class="modal-footer">            
                    <button type="button" class="btn btn-danger text-white" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success text-white" @click="Renew()">Confirm</button>
                </div>
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
    import draggable from 'vuedraggable';
    export default {
        components: {
            draggable,
        },
         mounted() {
               Vue.prototype.$userType = document.querySelector("meta[name='user-type']").getAttribute('content');
               Vue.prototype.$userSolde = document.querySelector("meta[name='user-solde']").getAttribute('content');
               this.test_active = document.querySelector("meta[name='testactive']").getAttribute("content");

            this.getPack();
            this.getResults();
            this.all_resellers(); 
                Fire.$on('AfterCreate' , () => {
                        this.getResults(); 
                        this.getPack();
                    }); 
       },
        data () {
        return {
        
            today : new Date().toISOString().slice(0, 10),
            tt: this.$userSolde,
            user_type:this.$userType,
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
            pack:'',
            name:'',
            days:'',
            duration:'',
            mac:'',
            time:'',
            notes:'',
            package_id:'',
        },

        errors: {
            mac:'',
            name:'',
            pack:'',
        },
        onLoad:true,
        selected_bouquet:[],
        resllers: [],
        byUser:'',
        test_active:1,
        filterVal:0,
        starStyle: {
            fullStarColor: '#ed8a19',
            emptyStarColor: '#737373',
            starWidth: 15,
            starHeight: 15
        },
        show_filter: false,
        cp_code:'',
        codeRenew: 0,
        packRenew: 0,
        monthRenew: 0,
      }
    },

        methods: {
            // Helper method to get bouquet name by ID
            getBouquetName(bouquetId, names) {
                const found = names.find(n => n.id == bouquetId);
                return found ? found.bouquet_name : '';
            },

            renewModal(number, package_id) {
                this.codeRenew = number;
                this.packRenew = package_id;
                $("#renewModal").modal("show");
            },

              //------------------------------------------DELETE FUNCTION ----------------------------------\\

            Renew(mac,package_id){
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
                            
                                    axios.put('mag/Devices/renew/'+this.codeRenew, {
                                        code: this.codeRenew,
                                        package_id: this.packRenew,
                                        month: this.monthRenew,
                                    })
                                    .then(() => {
                                    
                                        Swal.fire(
                                        'Renew!',
                                        'this MAG renew with success.',
                                        'success'
                                        )
                                    
                                    Fire.$emit('AfterCreate');
                                    }).catch((error) => {$
                                        if(error.response.data.msg) {
                                            toast.fire({
                                            icon: "error",
                                            title: "Votre solde est insuffissant !",
                                            });
                                        }else{
                                            Swal.fire(
                                        'Failed!',
                                        'There was something wronge',
                                        'warning'
                                        )
                                        }
                                        
                                    })
                            }
                        })
                    }else{
                        toast.fire({
                            icon: 'error',
                            title: 'Votre solde est insuffissant !'
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

            showModalDownload(mac){
                     $('#download').modal('show');
                     
                    this.type ='';
                    this.Show_m3u = false;
                    this.Show_m3u_plus = false;

                    this.m3u = '';
                    this.m3u_plus = '';

                        axios.post('mag/Devices/showM3U', {
                            mac:mac
                        })
                        
                        .then(response => {

                                this.m3u = response.data+'&type=m3u&output=ts';
                                this.m3u_plus = response.data+'&type=m3u_plus&output=ts';

                            }).catch(error => {})
                    
                    
                      
                },

            getpage(page = 1) {
                axios.post('mag/Devices_all?page=' + page)
                    .then(response => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
            },
            ResetMac(id){
                axios.put('mag/Devices/resetMac/'+id , this.code.mac)
                    .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Mac Adress reset in successfully'
                        })
                        
                        }).catch(error => {
                        
                        })
            
            },

            getPackiD(value, op) {                
                if (!this.editmode) {
                    axios
                    .post("code/GetPackID/" + value)
                    .then(
                        ({ data }) => (
                        (this.pack = data),
                        (this.selected_bouquet = this.pack.custom_bouquets)
                        )
                    );
                } else {
                    if(op == 1) {
                        axios
                        .post("code/GetPackID/" + value)
                        .then(
                            ({ data }) => (
                            (this.pack = data),
                            (this.selected_bouquet = this.pack.all_bouquets),
                            (this.pack.custom_bouquets = this.pack.all_bouquets)
                            )
                        );
                    }else{
                        axios
                        .post("code/GetPackID/" + value)
                        .then(
                        ({ data }) => (
                            (this.pack = data),
                            (this.selected_bouquet = this.pack.custom_bouquets),
                            (this.pack.custom_bouquets = this.code.selected_bouquets),
                            (this.pack.all_bouquets = this.code.pack != '' ? this.code.pack : this.pack.all_bouquets)
                        )
                        );
                    }
                    
                }
            },

             getPack() {
                 axios.post('code/GetPack').then(({ data }) => (this.packs = data));
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

            selectAll(e, p) {
                
                p.custom_bouquets = [];
                for (let k = 0; k < p.all_bouquets.length; k++) {
                    const element = p.all_bouquets[k];
                    if(e.target.checked) {
                        $('#bouquet_'+element).prop("checked", true);
                        p.custom_bouquets.push(element)
                    }else{
                        $('#bouquet_'+element).prop("checked", false);                        
                    }
                }
                this.selected_bouquet = p.custom_bouquets;                
            },

            
            onChange(value) {
               this.getPackiD(event.target.value, 1);


                if(this.pack.is_trial){
                   
                    this.NumStart = "160";
                    this.code.res = '';
                    this.code.number = '';
                
                
                }else {
                      this.NumStart = "190";
                       this.code.number = '';
                }

            },
            all_resellers() {
                axios.post("code/all_resellers").then(({ data }) => (this.resllers = data));
            },
            showResDet() {
                this.searchQuery = '';
                this.getResults();
            },
            filter(page = 1) {
                if(this.byUser != '') {
                    if(this.filterVal == 0) {
                    this.getResults();
                    }else if(this.filterVal == 1) {
                    axios
                    .post("code/magdevices/expired/"+this.byUser+"?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 2) {
                    axios
                    .post("code/magdevices/online/"+this.byUser+"?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 3) {
                    axios
                    .post("code/magdevices/almost_expired/"+this.byUser+"?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 4) {
                    axios
                    .post("code/magdevices/is_trial/"+this.byUser+"?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 5) {
                    axios
                    .post("code/magdevices/disabled/"+this.byUser+"?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 6) {
                    axios
                    .post("code/magdevices/enabled/"+this.byUser+"?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }
                }else{
                    if(this.filterVal == 0) {
                    this.getResults();
                    }else if(this.filterVal == 1) {
                    axios
                    .post("code/magdevices/expired?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 2) {
                    axios
                    .post("code/magdevices/online?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 3) {
                    axios
                    .post("code/magdevices/almost_expired?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 4) {
                    axios
                    .post("code/magdevices/is_trial?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 5) {
                    axios
                    .post("code/magdevices/disabled?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }else if(this.filterVal == 6) {
                    axios
                    .post("code/magdevices/enabled?page=" + page + "&query=" + this.searchQuery)
                    .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                    });
                    }
                }
            },
            getResults(page = 1) {
                if(this.byUser != '') {
                    if(this.filterVal == 0) {
                        axios
                        .post("code/mag_byres/"+this.byUser+"?page=" + page + "&query=" + this.searchQuery)
                        .then((response) => {
                        this.codes = response.data;
                        this.onLoad = false;
                        });
                    }else{
                        this.filter(page);
                    }
                }else{
                    if(this.filterVal == 0) {
                        axios.post('mag/Devices_all?page=' + page+'&query='+this.searchQuery)
                        .then(response => {
                            this.codes = response.data;
                            this.onLoad = false;
                        });
                    }else{
                        this.filter(page);
                    }
                }

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
                this.editmode = true
                this.getPackiD(code.package_id, 0);

                  if(code.is_trial){

                    this.NumStart = "160";
               
                
                }else {
                      this.NumStart = "190";
                }
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

                this.pack= {
                    id:'',
                    package_name:'',
                    official_duration:'',
                    bouquets:'',
                    official_duration_in:'',
                    is_trial:'',
                    trial_duration:'',
                    trial_duration_in:'',
                },
                this.errors= {
                    mac:'',
                    name:'',
                    pack:'',
                }
          },  

            //------------------------------------------show Active Code FUNCTION ----------------------------------\\

            ShowActiveCode (){
                axios.post('mag/Devices_all').then(({ data }) => (this.codes = data, this.onLoad = false));
            
            },
          //------------------------------------------CREATE FUNCTION ----------------------------------\\

            CreateActiveCode() { 
                this.pack.custom_bouquets = [];
                for (let k = 0; k < this.pack.all_bouquets.length; k++) {
                    const element = this.pack.all_bouquets[k];
                    if ($("#bouquet_" + element).prop("checked")) {
                    $("#bouquet_" + element).prop("checked", true);
                    this.pack.custom_bouquets.push(element);
                    } else {
                    $("#bouquet_" + element).prop("checked", false);
                    }
                }
                this.selected_bouquet = this.pack.custom_bouquets;
                axios({
                    url: 'code/getSolde',
                    method: 'POST',
                }).then((response) => {
                    if(this.pack.is_trial === 1) {
                        if(response.data.solde_test > 0  || this.$userType == "Admin"){
                            axios.post('mag/Devices', {
                                mac: this.code.mac,
                                pack: this.code.package_id,
                                name: this.code.name,
                                time: this.code.time,
                                duration: this.pack.official_duration,
                                duration_in: this.pack.official_duration_in,
                                notes: this.code.notes,
                                bouquets:JSON.stringify(this.selected_bouquet),
                                custom_bouquets: JSON.stringify(this.selected_bouquet),
                                pack_list: this.pack.all_bouquets,
                                is_trial : this.pack.is_trial,
                                trial_duration:  this.pack.trial_duration,
                                trial_duration_in:  this.pack.trial_duration_in,
                                password: Math.random().toString(36).substr(2, 10) ,
                                username : Math.random().toString(36).substr(2, 10) ,
                            
                                }).then(response => {
                                    Fire.$emit('AfterCreate');
                                    toast.fire({
                                        icon: 'success',
                                        title: 'Mag Device created in successfully'
                                    })
                                    $('#addnew').modal('hide');
                                }).catch(error => {
                                        if (error.response.status == 422){

                                            this.errors.name = error.response.data.errors.name;
                                            this.errors.mac = error.response.data.errors.mac;
                                            this.errors.pack = error.response.data.errors.pack;

                                        }else{
                                            if(error.response.data.msg) {
                                                toast.fire({
                                                icon: "error",
                                                title: "Votre solde est insuffissant !",
                                                });
                                            }
                                        }

                                })
                        }else{
                            toast.fire({
                                icon: 'error',
                                title: 'Votre solde de test est insuffissant !'
                            })
                            $('#addnew').modal('hide');
                        }
                    }else {
                        if(response.data.solde > 0  || this.$userType == "Admin"){
                            axios.post('mag/Devices', {
                                mac: this.code.mac,
                                pack: this.code.package_id,
                                name: this.code.name,
                                time: this.code.time,
                                duration: this.pack.official_duration,
                                duration_in: this.pack.official_duration_in,
                                notes: this.code.notes,
                                bouquets:JSON.stringify(this.selected_bouquet),
                                custom_bouquets: JSON.stringify(this.selected_bouquet),
                                pack_list: this.pack.all_bouquets,
                                is_trial : this.pack.is_trial,
                                trial_duration:  this.pack.trial_duration,
                                trial_duration_in:  this.pack.trial_duration_in,
                                password: Math.random().toString(36).substr(2, 10) ,
                                username : Math.random().toString(36).substr(2, 10) ,
                            
                                }).then(response => {
                                    Fire.$emit('AfterCreate');
                                    toast.fire({
                                        icon: 'success',
                                        title: 'Mag Device created in successfully'
                                    })
                                    $('#addnew').modal('hide');
                                    this.cp_code = '';
                                    if(this.pack.is_trial != 1 && this.pack.is_trial != "1") {
                                    $('#cpCode').modal('show');
                                    this.cp_code = response.data.code;
                                    } 
                                }).catch(error => {
                                        if (error.response.status == 422){

                                            this.errors.name = error.response.data.errors.name;
                                            this.errors.mac = error.response.data.errors.mac;
                                            this.errors.pack = error.response.data.errors.pack;

                                        }else{
                                            if(error.response.data.msg) {
                                                toast.fire({
                                                icon: "error",
                                                title: "Votre solde est insuffissant !",
                                                });
                                            }
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
                })
                
            
            },

          //------------------------------------------UPDATE FUNCTION ----------------------------------\\

            UpdateActiveCode() { 
                this.pack.custom_bouquets = [];
                for (let k = 0; k < this.pack.all_bouquets.length; k++) {
                    const element = this.pack.all_bouquets[k];
                    if ($("#bouquet_" + element).prop("checked")) {
                    $("#bouquet_" + element).prop("checked", true);
                    this.pack.custom_bouquets.push(element);
                    } else {
                    $("#bouquet_" + element).prop("checked", false);
                    }
                }
                this.selected_bouquet = this.pack.custom_bouquets;
                const headers={headers:{'Content-Type':'multipart/form-data'}};
                axios.put('mag/Devices/'+this.code.id,{
                    mac: this.code.mac,
                    pack: this.code.package_id,
                    name: this.code.name,
                    time: this.code.time,
                    duration: this.pack.official_duration,
                    duration_in: this.pack.official_duration_in,
                    username: this.code.res,
                    notes: this.code.notes,
                    bouquets:JSON.stringify(this.selected_bouquet),
                    pack_list: this.pack.all_bouquets,
                    is_trial : this.pack.is_trial,
                    trial_duration:  this.pack.trial_duration,
                    trial_duration_in:  this.pack.trial_duration_in,
                })
                   .then(response => {
                        Fire.$emit('AfterCreate');
                        toast.fire({
                            icon: 'success',
                            title: 'Mag Device Updated in successfully'
                        })
                        $('#addnew').modal('hide');
                    }).catch(error => {
                            if (error.response.status == 422){
                                this.errors.name = error.response.data.errors.name;
                                this.errors.mac = error.response.data.errors.mac;
                                this.errors.pack = error.response.data.errors.pack;
                            }
                    })
            
            },

        //------------------------------------------DELETE FUNCTION ----------------------------------\\

            DELETEActiveCode(id, type){
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
                    
                            axios.delete('mag/Devices/'+id+'/'+type)
                            .then((response) => {
                                if(type == 'disabled') {
                                    Swal.fire(
                                        'Disabled!',
                                        'this MAG has been disabled.',
                                        'success'
                                    )
                                }else if(type =="delete") {
                                    Swal.fire(
                                        'Deleted!',
                                        'this MAG has been deleted.',
                                        'success'
                                    )
                                } else {
                                    Swal.fire({ html: "this mag device transferd to user.<br> login: "+response.data.login + "<br> password : "+response.data.password, title: 'Transferd!'});
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

            EnabledMagDevice(id) {
                axios.post('code/EnableMD/'+id)
                .then(response => {
                    Fire.$emit('AfterCreate');
                });
            },

            changeDays(e, mac) {
                axios.post('changeDays/magdevice/'+mac, {
                    days:e.target.value
                })
                    .then(response => {
                        Fire.$emit('AfterCreate');
                    });
            },

            getImgUrl(pet) {
                
                var images = '/assets/img/flags/' + pet + ".png"
                return images
            },
            uperCaseMAC() {
                let f = this.code.mac;
                f = f.toString();
                f = f.toUpperCase();
                this.code.mac = f;
            },

            copyCode(c_code) {
                const el = document.createElement('textarea');
                el.value = c_code;
                document.body.appendChild(el);
                el.select();
                el.setSelectionRange(0, el.value.length);
                navigator.clipboard
                .writeText(el.value)
                .then(() => {
                    window.alert('Copied to clipboard!');
                })
                .catch((e) => {
                    alert(e);
                });
                document.body.removeChild(el);
            }
        },

        
        

         created() {
            // Event listener only - API calls already in mounted()
        }
    }
</script>


