<?php /* #?ini charset="utf-8"?

[Transformation]
Groups[]=urlalias_dataset

[urlalias_dataset]
# Extra transformation files for urlalias
Files[]
# Extensions that have transformation files when urlalias is used
Extensions[]
# The commands to use for search
Commands[]
Commands[]=normalize
Commands[]=transform
Commands[]=decompose
Commands[]=transliterate
Commands[]=diacritical
Commands[]=lowercase
Commands[]=url_cleanup


*/ ?>
