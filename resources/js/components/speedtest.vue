<template>
    <div>
        <iframe width="100%" height="650px" frameborder="0" src="https://newiptv.speedtestcustom.com" style="width: 100vw; height: 100vh;"></iframe>
    </div>
</template>

<script>
import videojs from 'video.js';

export default {
    name: "speedtest",
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