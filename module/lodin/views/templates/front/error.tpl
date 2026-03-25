<div class="alert alert-danger">
    <h4>{l s='Payment Error' mod='lodin'}</h4>
    <p>{l s='An error occurred: %s' mod='lodin' sprintf=[$error|escape:'html':'UTF-8']}</p>
    <a href="{$urls.pages.order_history}" class="btn btn-primary">{l s='Back to Orders' mod='lodin'}</a>
</div>
