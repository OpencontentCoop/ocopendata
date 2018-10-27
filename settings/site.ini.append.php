<?php /*

[TemplateSettings]
ExtensionAutoloadPath[]=ocopendata

[RoleSettings]
PolicyOmitList[]=opendata/console
PolicyOmitList[]=opendata/analyzer
PolicyOmitList[]=opendata/help

[Cache]
CacheItems[]=ocopendataapiclasses
CacheItems[]=ocopendataapistates
CacheItems[]=ocopendataapicontent

[Cache_ocopendataapiclasses]
name=Opendata Api classi
id=ocopendata_classes
tags[]=ocopendata
path=ocopendata/class
isClustered=true
class=OCOpenDataClassRepositoryCache

[Cache_ocopendataapicontent]
name=Opendata Api contenuti
id=ocopendata_content
tags[]=ocopendata
path=ocopendata/content
isClustered=true
class=OCOpenDataContentRepositoryCache


[Cache_ocopendataapistates]
name=Opendata Api Stati
id=ocopendata_states
tags[]=ocopendata
path=ocopendata/states.cache
isClustered=true
class=OCOpenDataStateRepositoryCache

[Event]
Listeners[]=content/class/cache@OCOpenDataClassRepositoryCache::clearCache

*/ ?>
