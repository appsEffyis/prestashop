{*
 * Lodin RTP Payment Module
 *
 * @author    Lodin <apps@lodinpay.com>
 * @copyright 2026 Lodin
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 *}
{extends file='page.tpl'}

{block name='page_content'}
    <section id="content" class="page-content card card-block">
        <div class="alert alert-danger">
            <h4>{l s='Payment Error' mod='lodin'}</h4>
            <p>{l s='An error occurred: %s' mod='lodin' sprintf=[$error|escape:'html':'UTF-8']}</p>
        </div>

        <div class="mt-3">
            <a href="{$urls.pages.order_history|escape:'html':'UTF-8'}" class="btn btn-primary">
                {l s='Back to Orders' mod='lodin'}
            </a>
        </div>
    </section>
{/block}