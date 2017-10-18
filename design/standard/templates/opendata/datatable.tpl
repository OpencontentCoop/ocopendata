<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Datatable</title>

    <link href="http://getbootstrap.com/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css"
          href="/extension/ocopendata/design/standard/stylesheets/dataTables.bootstrap.css">
    <link rel="stylesheet"
          href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css">

    <script src="http://code.jquery.com/jquery-1.12.0.min.js"
            type="application/javascript"></script>
    <script src="http://getbootstrap.com/dist/js/bootstrap.min.js"></script>
    <script src="/extension/ocopendata/design/standard/javascript/jquery.dataTables.js"></script>
    <script src="/extension/ocopendata/design/standard/javascript/jquery.opendataDataTable.js"></script>
    <script src="/extension/ocopendata/design/standard/javascript/jquery.opendataTools.js"></script>
    <script src="/extension/ocopendata/design/standard/javascript/dataTables.bootstrap.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.11.1/moment.min.js"></script>

    <script type="text/javascript" language="javascript" class="init">
        {literal}

        $(document).ready(function () {
            $('.container-fluid').opendataDataTable({
                "builder":{
                  "query": '*'
                },
                "datatable":{
                    "ajax": {
                        url: "{/literal}{'opendata/api/datatable/search'|ezurl(no,full)}{literal}/"
                    },
                    "columns": [
                        {"data": "metadata.id", "name": 'id', "title": 'ID'},
                        {"data": "metadata.published", "name": 'published', "title": 'Published'},
                        {
                            "data": "metadata.classIdentifier",
                            "name": 'class',
                            "title": 'Class'
                        },
                        {"data": "metadata.name.ita-IT", "name": 'name', "title": 'Name'}
                    ]
                }
            }).data('opendataDataTable').loadDataTable();
        });
        {/literal}
    </script>

</head>

<body>
<div class="container-fluid">
</div>
</body>
</html>