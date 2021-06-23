{def $state=array( 'old', 'new' )
     $counter=0}
{foreach array( $diff.old_content, $diff.new_content ) as $attr}
    <div class="attribute-view-diff-{$state[$counter]}">
        {set $counter=inc( $counter )}
        <label>{'Version %ver'|i18n( 'design/standard/content/datatype',, hash( '%ver', $attr.version ) )}:</label>
        <p><code>{$attr.data_text}</code></p>
    </div>
{/foreach}