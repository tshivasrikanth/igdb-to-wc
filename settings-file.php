<h1>IGDB Settings</h1>
<form method="POST">
    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="igdbapikey">API KEY</label>
            </th>
            <td>
                <input type="text" class="required regular-text" name="igdbapikey" id="apikey" value="<?php echo $value; ?>">
            </td>
        </tr>
        <tr>
            <th scope="row">
                <?php wp_nonce_field( 'IgdbNonce' ); ?>
                <input type="submit" value="Submit" class="button button-primary button-large">
            </th>
            <td></td>
        </tr>   
</table>
</form>