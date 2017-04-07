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

[Cache_ocopendataapiclasses]
name=Opendata Api classi
id=ocopendata_classes
tags[]=ocopendata
path=ocopendata/class
isClustered=true
class=OCOpenDataClassRepositoryCache

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
