{extends file='page.tpl'}

{block name='page_content'}
    <section id="content" class="page-content card card-block">
        <div class="alert alert-danger">
            {l s='Désolé, votre paiement n\'a pas pu être traité.' d='Modules.Lodin.Shop'}
        </div>
        
        <p>{l s='Il semble y avoir eu un problème lors de la transaction. Aucun montant n\'a été débité (ou celui-ci sera remboursé).' d='Modules.Lodin.Shop'}</p>
        
        <div class="mt-3">
            <a href="{$checkout_url}" class="btn btn-primary">
                {l s='Réessayer avec un autre moyen de paiement' d='Modules.Lodin.Shop'}
            </a>
        </div>
    </section>
{/block}