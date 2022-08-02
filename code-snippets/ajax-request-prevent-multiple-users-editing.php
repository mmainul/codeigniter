<script>
    $(document).ready(function(){
        var url = window.location;
        var urlArr = url.toString().split('/');
        var urlArrLen = urlArr.length;
        var urlIncludeUpdate = 'update';
        if(urlIncludeUpdate === urlArr[urlArrLen - 2]){
            var callFunTime = 1000*60*5;
            function update_locked_time(){
                $.ajax({
                    url: '/report/prevent_multiusers_edit/update_time_locked',
                    type: 'POST',
                    data: {request_id: urlArr[urlArrLen-1], report_type: urlArr[urlArrLen-3]},
                    cache:false,
                    error: function() {
                        console.log('Something is wrong');
                    },
                    success: function(res) {
                        console.log('Locked time updated');
                    }
                });
            } // end function

            update_locked_time();

            setInterval(update_locked_time,callFunTime);
        } // end if
    });
</script>
