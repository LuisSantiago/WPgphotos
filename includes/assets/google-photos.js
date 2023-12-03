jQ = jQuery;
jQ(window).load(function () {
    jQ('.google-photos-spinner').hide();
    jQ("#getPhotos").click(function (event) {
        jQ(this).hide();
        event.preventDefault();
        jQ('.google-photos-spinner').show();
        jQ.post("/wp-admin/admin-ajax.php", {      //POST request
                action: "getAlbumsAjax",         //action
                title: this.value               //data
            }, function (data) {
                jQ('.google-photos-spinner').hide();
                jQ('#getPhotos').hide();
                jQ.each(JSON.parse(data), function (index, album) {
                    console.log(album);
                    jQ('#albumsSelector').append('<a class="custom_button albumSelect" id="' + album.id + '">' + album.title + '</a>');
                });
                jQ('#albumsSelector .albumSelect').click(function (event) {
                    console.log(this);
                    event.preventDefault();
                    jQ('#googleAlbum').attr('value', this.id);
                })
            }
        );
    });

});

