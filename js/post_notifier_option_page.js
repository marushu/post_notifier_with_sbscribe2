( function( $ ) {

    const countChecked = function() {

        const n = $( "input.debug_check:checked" ).length;
        const text_target = $( '#here' );
        const text_area = $( '.all_email ' );

        $( text_target ).html( 'Set e-mailフィールドへテスト送信するメールアドレスを入力してください。<br>デバッグモードをオフにするにはチェックボックスのチェックを外して保存してください。デフォルトで送信するメールアドレスを復元します。' );

        if ( n > 0 && ! $( text_area ).hasClass( 'now_debuging' ) ) {

            const all_email = $( '.all_email' ).val( '' );

        } else {

            $( text_target ).text( '' );

        }

    };
    countChecked();

    $( ".debug_check_label" ).on( "click", countChecked );

}) ( jQuery );