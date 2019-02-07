Le OpenData API offrono l'accesso in lettura dei contenuti, l'accesso alla definizione delle classi e l'accesso all'elenco delle sezioni e degli stati dei contenuti.

.. _h1f494a2c4c1d7c2f6e43675269382e:

Accesso alle API
================

E' possibile accedere alle API attraverso una richiesta HTTP al server API. Il server restituirà la risorsa richiesta con uno status code HTTP coerente con il risultato della richiesta fatta.

.. _h105774c3c5046436e32213841d1bc:

Formato della risposta
======================

Il formato di default della risposta dei moduli content è \ |LINK1|\ . Il formato di default della risposta dei moduli geo è \ |LINK2|\ .

.. _h2c69b7a452d1975d316a674293676:

Autenticazione
==============

Alcune richieste richiedono che il client effettui l'autenticazione. Al momento è disponibile l'autenticazione \ |LINK3|\ , è in roadmap l'implementazione del formato Oauth/Oauth2.

.. _h797b1f1458373256666d3d6237e658:

Lettura di un contenuto
=======================


.. code:: 

    GET content/read/$ContentIdentifier

La risposta in JSON è un oggetto \ |LINK4|\ 

La varibile obbligatoria $ContentIdentifier rappresenta un identificatore unico del contentuto, espresso con metadata.id oppure metadata.remoteId

.. _h7e6b181472f5f3a712e575f75294c40:

Ricerca di un contenuto
=======================


.. code:: 

    GET content/search/$Query

La risposta in JSON è un oggetto \ |LINK5|\ 

La varibile obbligatoria $Query rappresenta la stringa di ricerca. Il server API riconosce \ |LINK6|\  per filtrare i contenuti.

.. _h413a4c412d6971386e6b3d516b274151:

Lettura di un punto geografico
==============================


.. code:: 

    GET geo/read/$ContentIdentifier

La risposta in geoJSON è un oggetto \ |LINK7|\ 

La varibile obbligatoria $ContentIdentifier rappresenta un identificatore unico del contentuto.

.. _h2d5835306b1a71162a6662a233c:

Ricerca di punti geografici
===========================


.. code:: 

    GET geo/search/$Query

La risposta in geoJSON è un oggetto \ |LINK8|\ 

La varibile obbligatoria $Query rappresenta la stringa di ricerca. Il server API riconosce un meta-linguaggio per filtrare i contenuti.

.. _h5b1165e6e664357739590136e3c64:

Rappresentazione delle risorse
==============================

.. _hf5c1c52787e44753f5040467e1f7e61:

Content
-------

\ |LINK9|\ 

La risorsa contenuto rappresenta il cuore delle API. Essa viene esposta come un oggetto composto da due proprietà che ne rappresentano i metadati e i dati veri e propri.

+------------+------------+-------------------------------------------+
|\ |STYLE0|\ |\ |STYLE1|\ |\ |STYLE2|\                                |
+------------+------------+-------------------------------------------+
|metadata    |object      |Rappresentazione dei metadati del contenuto|
+------------+------------+-------------------------------------------+
|data        |object      |Rappresentazione dei dati del contenuto    |
+------------+------------+-------------------------------------------+

.. _h322147a4c631d1976443d3924383f7d:

Metadata
--------

I metadati sono informazioni non riferibili al contentuto vero e proprio dell'oggetto ma al suo contesto. Attraverso i metadati è possibile raggiungere informazioni utiliu per la ricerca quali l'identificativo unico della risorsa, la classe di contenuto utilizzata, le date di pubblicazione e di modifica...

+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|\ |STYLE3|\      |\ |STYLE4|\  |\ |STYLE5|\                                                                                                                                                            |\ |STYLE6|\ |
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|id               |integer      |Id univoco del contenuto nel server API                                                                                                                                |Sola lettura|
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|remoteId         |string       |Id univoco del contenuto nel server API impostato dal redattore o dal processo di importazione automatica: per design la sua lunghezza non può superare i 100 caratteri|            |
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|classIdentifier  |string       |Identificatore della classe di contenuto utilizzata dalla risorsa esposta                                                                                              |            |
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|class            |uri          |Url di accesso alla risorsa classe utilizzata dalla risorsa esposta                                                                                                    |Sola lettura|
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|sectionIdentifier|string       |Identificatore della sezione utilizzata dalla risorsa esposta                                                                                                          |            |
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|stateIdentifiers |array        |Array degli identificatori di stato (nel formato <stategroup_identifier>.<state_identifier> utilizzati dalla risorsa esposta                                           |            |
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|published        |Date ISO 8601|Data di pubblicazione del contenuto                                                                                                                                    |Sola lettura|
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|modified         |Date ISO 8601|Data di ultima modifica del contenuto                                                                                                                                  |Sola lettura|
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|languages        |array        |Array degli identificatori di lingua delle traduzioni disponibili della risorsa esposta                                                                                |Sola lettura|
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|name             |hash         |Array associativo del nome della risorsa per ciascuna traduzione disponibile                                                                                           |Sola lettura|
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|parentNodes      |array        |Array dei Id dei Nodi in cui è collocato l'oggetto informativo                                                                                                         |            |
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+
|link             |uri          |Url di accesso alla risorsa richiesta                                                                                                                                  |Sola lettura|
+-----------------+-------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------+------------+

.. _ha673562925505a7e531e3b5a484512:

Data
----


+--------------------------+------------+----------------------------------------------+
|\ |STYLE7|\               |\ |STYLE8|\ |\ |STYLE9|\                                   |
+--------------------------+------------+----------------------------------------------+
|(Identificatore di lingua)|object      |Rappresentazione degli attributi del contenuto|
+--------------------------+------------+----------------------------------------------+

La risorsa Data espone il contenuto informativo del Content per ciascuna traduzione disponibile. Ad ogni identificatore di lingua corrisponde da un oggetto chiave-valore dove ciascuna chiave è l'identificatore dell'attributo e ciascun valore è rappresentato da un tipo di dato primitivo o strutturato. Data la natura flessibile del content model di eZPublish è necessario ricavare la struttura della risorsa Data attravero la definizione della classe raggiungibile dall'url esposto in content.metadata.class .

.. _h2e49a347125268707f582534244758:

SearchResults
-------------


+-------------+------------------------------------+-------------------------------------------------------+
|\ |STYLE10|\ |\ |STYLE11|\                        |\ |STYLE12|\                                           |
+-------------+------------------------------------+-------------------------------------------------------+
|query        |string                              |Stringa di ricerca                                     |
+-------------+------------------------------------+-------------------------------------------------------+
|nextPageQuery|string oppure null                  |Stringa per ricevere la pagina successiva dei risultati|
+-------------+------------------------------------+-------------------------------------------------------+
|totalCount   |integer                             |Numero totale di contenuti ottenuti dalla ricerca      |
+-------------+------------------------------------+-------------------------------------------------------+
|searchHits   |Array di Content oppure \ |LINK10|\ |Risultati della ricerca                                |
+-------------+------------------------------------+-------------------------------------------------------+

.. _h1b33d1b9275c4253722d78662685f:

La query di ricerca
===================

Per eseguire una query occorre specificare una stringa di ricerca Query.

La Query viene effettuata attraverso i filtri e i parametri.

.. _h58741972623344267c5b3d1185f2a10:

Filtri
------

I filtri sono composti dai un identificatore, un operatore e un valore.

Ad esempio:

.. code:: 

    titolo = 'Mercatino di Natale'

In questo esempio, il campo è "titolo", l'operatore è "=" e il valore è "'Mercatino di Natale'". Si sta chiedendo al motore di ricerca di restituire tutti i contenuti che hanno un attributo il cui è identificatore "titolo" contiene il valore 'Mercatino di Natale'.

.. _h771e717b522d3426a71277117387039:

Operatori per i filtri
----------------------


+-------------------------------------+--------------------------+-------------------------------------------------------------------------------------------+
|\ |STYLE13|\                         |\ |STYLE14|\              |\ |STYLE15|\                                                                               |
+-------------------------------------+--------------------------+-------------------------------------------------------------------------------------------+
|=                                    |Stringa compresa tra apici|titolo = 'Nel mezzo del cammin'                                                            |
+-------------------------------------+--------------------------+-------------------------------------------------------------------------------------------+
|!=                                   |Stringa compresa tra apici|titolo != 'Nel mezzo del cammin'                                                           |
+-------------------------------------+--------------------------+-------------------------------------------------------------------------------------------+
|in e la sua negazione !=             |Array di stringhe         |titolo in ['Nel mezzo del cammin di nostra vita','La gloria di colui che tutto move']      |
+-------------------------------------+--------------------------+-------------------------------------------------------------------------------------------+
|contains e la sua negazione !contains|Array di stringhe         |titolo contains ['Nel mezzo del cammin di nostra vita','La gloria di colui che tutto move']|
+-------------------------------------+--------------------------+-------------------------------------------------------------------------------------------+
|range e la sua negazione !range      |Array di 2 stringhe       |from_time range [2014-01-01,2014-12-31]                                                    |
+-------------------------------------+--------------------------+-------------------------------------------------------------------------------------------+

L'operatore "contains" produce in AND logico: tutti i titoli che contengono le stringhe 'Nel mezzo del cammin di nostra vita' e 'La gloria di colui che tutto move' contemporaneamente.

L'operatore "in" produce in OR logico: tutti i titoli che contengono la stringa 'Nel mezzo del cammin di nostra vita' oppure la stringa 'La gloria di colui che tutto move'.

.. _h7254151f72c753d526a176a17b4769:

Parametri
---------


+-------------+---------------------------+--------------------------------------------------+--------------------------------------------------------+
|\ |STYLE16|\ |\ |STYLE17|\               |\ |STYLE18|\                                      |\ |STYLE19|\                                            |
+-------------+---------------------------+--------------------------------------------------+--------------------------------------------------------+
|sort         |Hash                       |sort [published => desc]                          |Ordinamento                                             |
+-------------+---------------------------+--------------------------------------------------+--------------------------------------------------------+
|limit        |intero                     |limit 10                                          |Numero di risultati per pagina (massimo 100, default 30)|
+-------------+---------------------------+--------------------------------------------------+--------------------------------------------------------+
|offset       |intero                     |offset 10                                         |Offset per la paginazione                               |
+-------------+---------------------------+--------------------------------------------------+--------------------------------------------------------+
|classes      |stringa o Array di stringhe|classes 'event' oppure classes ['event','article']|restrizione sui tipi di contenuto                       |
+-------------+---------------------------+--------------------------------------------------+--------------------------------------------------------+
|subtree      |Array di interi            |subtree [2,43,54]                                 |restrizione di sotto albero                             |
+-------------+---------------------------+--------------------------------------------------+--------------------------------------------------------+

I parametri servono a modificare l'ambito di ricerca e sono rappresentati da una chiave e da un valore. 

.. code:: 

    classes 'event'

In questo esempio la chiave è "classes" e il valore "’event'". Si sta chiedendo al motore di ricerca di restituire tutti contenuti di classe "event".

Ecco un esempio che usa filtri e parametri: 

.. code:: 

    titolo = 'Mercatino di Natale' classes 'event'

E' possibile eseguire ricerche più complesse. Ad esempio per ricerca gli eventi della settimana prossima:

.. code:: 

    from_time range [today,next week] or to_time range [today,next week] or ( from_time range [*,today] and to_time range [next week,*] ) classes event sort [published => desc]

Il server API mette a disposizione di default una console raggiungibile da (\ |LINK11|\ )


.. bottom of content


.. |STYLE0| replace:: **Identificatore**

.. |STYLE1| replace:: **Tipo di dato**

.. |STYLE2| replace:: **Descrizione**

.. |STYLE3| replace:: **Identificatore**

.. |STYLE4| replace:: **Tipo di dato**

.. |STYLE5| replace:: **Descrizione**

.. |STYLE6| replace:: **Note**

.. |STYLE7| replace:: **Identificatore**

.. |STYLE8| replace:: **Tipo di dato**

.. |STYLE9| replace:: **Descrizione**

.. |STYLE10| replace:: **Identificatore**

.. |STYLE11| replace:: **Tipo di dato**

.. |STYLE12| replace:: **Descrizione**

.. |STYLE13| replace:: **Operatore**

.. |STYLE14| replace:: **Tipo di valore atteso**

.. |STYLE15| replace:: **Esempio**

.. |STYLE16| replace:: **Operatore**

.. |STYLE17| replace:: **Tipo di valore atteso**

.. |STYLE18| replace:: **Esempio**

.. |STYLE19| replace:: **Utilizzo**


.. |LINK1| raw:: html

    <a href="http://www.json.org/" target="_blank">JSON</a>

.. |LINK2| raw:: html

    <a href="http://geojson.org/" target="_blank">geoJSON</a>

.. |LINK3| raw:: html

    <a href="https://it.wikipedia.org/wiki/Basic_access_authentication" target="_blank">Basic</a>

.. |LINK4| raw:: html

    <a href="#heading=h.bf1ehngwldck">Content</a>

.. |LINK5| raw:: html

    <a href="#heading=h.b5klwxoizuzw">SearchResults</a>

.. |LINK6| raw:: html

    <a href="#heading=h.44opkufuw637">un meta-linguaggio</a>

.. |LINK7| raw:: html

    <a href="http://geojson.org/geojson-spec.html#feature-objects" target="_blank">Feature</a>

.. |LINK8| raw:: html

    <a href="http://geojson.org/geojson-spec.html#feature-collection-objects" target="_blank">FeatureCollection</a>

.. |LINK9| raw:: html

    <a href="https://github.com/Opencontent/openservices/blob/master/doc/example/content.json" target="_blank">Esempio di content in formato json</a>

.. |LINK10| raw:: html

    <a href="http://geojson.org/geojson-spec.html#feature-collection-objects" target="_blank">FeatureCollection</a>

.. |LINK11| raw:: html

    <a href="http://www.domain.tdl/opendata/console" target="_blank">www.domain.tdl/opendata/console</a>

