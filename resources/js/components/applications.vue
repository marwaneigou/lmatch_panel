<template>
    <div class="container">
        <div class="row">
            <h1>
                {{trans('app_activation')}}
            </h1>
        </div>
        <div class="row">
            <div class="panel-body col-12 text-center my-5">
                <h1 v-if="this.$userSoldeApp == '0' && this.$userType !='Admin'">{{trans('no_app_credit')}} </h1> 
                <router-link to="/players/arcplayer" v-if="this.$userSoldeApp != '0' || this.$userType =='Admin'">
                    <img
                        src="assets/img/arc.png"
                        title="ARC Player"
                        style="width: 126px;"
                        alt=""
                    />
                </router-link>
                <router-link to="#" v-if="this.$userSoldeApp != '0' || this.$userType =='Admin'">
                    <img
                    src="assets/img/icons ibo.png"
                    title="IBO IPTV"
                    style="width: 126px;"
                    alt=""
                    />
                </router-link>
            </div>
        </div>
    </div>    
</template>

<script>
import videojs from 'video.js';

export default {
    name: "applications",
    props: {
        options: {
            type: Object,
            default() {
                return {};
            }
        }
    },
    data() {
        return {
        }
    },
    mounted() {
    },
    beforeDestroy() {
    }
}
</script>