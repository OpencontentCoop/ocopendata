<div class="global-view-full">

    {if is_set( $class )}
        <h1>{$class.name['ita-IT']}</h1>

        <p>{$class.description['ita-IT']}</p>

        <h2>Descrizione dei campi</h2>
        <table width="100%" cellspacing="0" cellpadding="0" border="0" class="table table-striped list">
            <thead>
            <tr>
                <th style="vertical-align: middle">Attributo</th>
                <th style="vertical-align: middle">Identificatore</th>
                <th style="vertical-align: middle">Descrizione</th>
                <th style="vertical-align: middle">Tipo di dato</th>
                <th style="vertical-align: middle">Formato del dato</th>
            </tr>
            </thead>
            <tbody>
            {foreach $class.fields as $field sequence array(bglight,bgdark) as $style}
                <tr id="{$field.identifier}" class="class {$style}">
                    <td style="vertical-align: middle">
                        {$field.name['ita-IT']}
                    </td>
                    <td style="vertical-align: middle">
                        {$field.identifier}
                    </td>
                    <td>{$field.description['ita-IT']}</td>
                    <td>{$field.dataTypeName} ({$field.dataType})</td>
                    <td>{if is_set( $field.template.type )}{$field.template.type}{else}string{/if}</td>
                </tr>
            {/foreach}
            </tbody>
        </table>

    {else}
        <h1>Classi di contenuto</h1>
        <table width="100%" cellspacing="0" cellpadding="0" border="0" class="list">
            <thead>
            <tr>
                <th style="vertical-align: middle">Classe</th>
                <th style="vertical-align: middle">Identificatore</th>
                <th style="vertical-align: middle">Descrizione</th>
            </tr>
            </thead>
            <tbody>
            {foreach $classes as $class sequence array(bglight,bgdark) as $style}
                <tr id="{$class.identifier}" class="class {$style}">
                    <td style="vertical-align: middle;white-space: nowrap">
                        <a href={concat('/opendata/help/classes/',$class.identifier)|ezurl()}>
                            {$class.name['ita-IT']}
                        </a>
                    </td>
                    <td style="vertical-align: middle">
                        {$class.identifier}
                    </td>
                    <td>{$class.description['ita-IT']}</td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    {/if}

</div>