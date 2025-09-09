{if $is_hot}
    <span class="hotproduct">
        {l s='🔥 More than %d purchased in the last %d days' sprintf=[$nb_sales_rounded, $nb_days] mod='hotproduct'}
    </span>
{/if}