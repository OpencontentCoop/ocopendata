<?php

use Opencontent\Opendata\Api\ClassRepository;

/** @var eZModule $module */
$module = $Params['Module'];
$tpl = eZTemplate::factory();
$section = $Params['Section'];
$identifier = $Params['Identifier'];

try
{
    $classRepository = new ClassRepository();
    if ( $section == 'classes' )
    {
        $class = null;

        if ( $identifier )
        {
            $class = (array)$classRepository->load( $identifier );
            $tpl->setVariable( 'class', $class );
        }
        else
        {
            $classes = array();
            $list = $classRepository->listAll();
            foreach ( $list as $item )
            {
                $classes[$item['identifier']] = (array)$classRepository->load(
                    $item['identifier']
                );
            }
            $tpl->setVariable( 'classes', $classes );
        }

        $Result = array();
        $Result['content'] = $tpl->fetch( 'design:help/classes.tpl' );
        $Result['node_id'] = 0;
        $contentInfoArray = array( 'url_alias' => 'opendata/help', 'class_identifier' => null );
        $contentInfoArray['persistent_variable'] = array(
            'show_path' => true
        );
        $Result['content_info'] = $contentInfoArray;
        $Result['path'] = array(
            array(
                'text' => 'Informazioni aulle classi',
                'url' => 'opendata/help/' . $section,
                'node_id' => null
            )
        );
        if ( $identifier && $class )
        {
            $Result['path'][] = array(
                'text' => $class['name']['ita-IT'],
                'url' => false,
                'node_id' => null
            );
        }
    }
    else
    {
        throw new Exception( "Section not found" );
    }
}
catch ( Exception $e )
{
    eZDebug::writeError( $e->getMessage(), __FILE__ );

    return $module->handleError( eZError::KERNEL_NOT_AVAILABLE, 'kernel' );
}


