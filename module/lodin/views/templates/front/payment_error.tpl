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
            {l s='Sorry, your payment could not be processed.' mod='lodin'}
        </div>

        <p>{l s='There seems to have been a problem with the transaction. No amount has been debited.' mod='lodin'}</p>

        <div class="mt-3">
            <a href="{$checkout_url|escape:'html':'UTF-8'}" class="btn btn-primary">
                {l s='Try again with another payment method' mod='lodin'}
            </a>
        </div>
    </section>
{/block}