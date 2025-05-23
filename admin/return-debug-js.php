<script>
$(document).ready(function() {
    $('#billingnumber').on('input', function() {
        const billNum = $(this).val().trim();
        if (billNum.length >= 3) {
            $.post('ajax/validate-billing-simple.php', {billingnumber: billNum}, function(data) {
                if (data.valid) {
                    $('#productid').html(data.productOptions).prop('disabled', false);
                    $('#billing-info').html(data.customerInfo).show();
                }
            }, 'json');
        }
    });
    
    $('#productid').on('change', function() {
        const productId = $(this).val();
        const billNum = $('#billingnumber').val();
        if (productId && billNum) {
            $.post('ajax/get-product-details-simple.php', {
                productid: productId, 
                billingnumber: billNum
            }, function(data) {
                if (data.success) {
                    $('#product-details').html(data.details).show();
                }
            }, 'json');
        }
    });
});
</script>