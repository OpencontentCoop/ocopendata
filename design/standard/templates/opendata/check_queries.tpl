<div class="row">
  <div class="col-12">
    <h1 class="h3 pt-3">Controllo delle query nei blocchi opendata<span></span></h1>
  </div>
</div>
<div class="row">
  <div id="spinner" class="col-xs-12 text-center" style="display: none">
    <i aria-hidden="true" class="fa fa-circle-o-notch fa-spin fa-3x fa-fw"></i>
    <span class="sr-only">{'Loading...'|i18n('editorialstuff/dashboard')}</span>
  </div>
  <div class="col-3">
    <input type="text"
           class="form-control pl-0 ps-0 mb-2"
           id="object-search"
           name="SearchText"
           value=""
           placeholder="{'Search text'|i18n('bootstrapitalia/documents')}"/>
    <div class="toggles" id="filterToggle">
      <label for="only-fields" style="line-height: 1px;text-align:center">
        <input type="checkbox" id="only-fields" name="ShowOnlyWithFields" />
        <span class="lever" style="margin-top: 5px;display: block;float:left"></span>
        <span style="margin-top: 12px;display: block;float:left">Solo con filtri</span>
      </label>
    </div>
    <div class="toggles" id="errorToggle">
      <label for="only-error" style="line-height: 1px;text-align:center">
        <input type="checkbox" id="only-error" name="ShowOnlyWithError" />
        <span class="lever" style="margin-top: 5px;display: block;float:left"></span>
        <span style="margin-top: 12px;display: block;float:left">Solo con errori</span>
      </label>
    </div>
    <ul class="nav nav-tabs nav-tabs-vertical" role="tablist" aria-orientation="vertical" id="object-list"></ul>
  </div>
  <div class="col-9 tab-content" id="query-list"></div>
</div>

<div id="test-modal" class="modal fade" style="z-index:10000">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-body">
        <div class="clearfix">
          <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-hidden="true" aria-label="{'Close'|i18n('bootstrapitalia')}" title="{'Close'|i18n('bootstrapitalia')}">&times;</button>
        </div>
        <div id="test-content" class="list-group pb-4"></div>
      </div>
    </div>
  </div>
</div>

{literal}
  <script id="tpl-object" type="text/x-jsrender">
    <li class="nav-item">
      <a class="nav-link ps-0 d-block" data-toggle="tab" data-bs-toggle="tab" data-focus-mouse="false" href="#panel-{{:object_id}}">
      <span class="h6 d-block">{{:object_name}}</span>
      <small>{{:object_class_name}} ({{for attributes}}{{:name}}{{/for}})</small>
      <span class="d-none">{{:object_id}} {{:object_remote_id}}</span>
      </a>
    </li>
  </script>

  <script id="tpl-query" type="text/x-jsrender">
    <div class="tab-pane" id="panel-{{:object_id}}">
      <h3><a class="" href="/openpa/object/{{:object_id}}" target="_blank">{{:object_name}}</a></h3>
      {{for attributes}}
        {{for blocks}}
        <div class="mb-4 border rounded p-5" data-query-group>
          {{for languages}}
            <div class="form-group mb-2" data-query>
              <label class="form-label h6 ps-0">{{:block_name}} ({{:block_index}})</label>
              {{if error}}<div class="bg-danger rounded p-2 my-2"><span class="text-white">{{:error}}</span></div>{{/if}}
              <div class="input-group flex-nowrap">
                <button class="btn btn-outline-secondary btn-xs" type="button"><img src="/share/icons/flags/{{:attribute_language}}.gif" width="18" height="12" alt="{{:attribute_language}}"></button>
                <textarea class="form-control"
                  data-attribute_id="{{:attribute_id}}"
                  data-attribute_version="{{:attribute_version}}"
                  data-attribute_language="{{:attribute_language}}"
                  data-zone_identifier="{{:zone_identifier}}"
                  data-block_id="{{:block_id}}"
                  style="height: 75px;border: 2px solid hsl(210,17%,44%) !important;border-left: none !important;" class="form-control">{{>query}}</textarea>
              </div>
              <div class="text-end mt-2">
                <button class="btn btn-secondary btn-xs me-2" data-trigger="test" type="button" data-url="{{:search_url}}">Test</button>
                <button class="btn btn-secondary btn-xs" data-trigger="optimize" type="button">Ottimizza</button>
              </div>
            </div>
          {{/for}}
          <div class="text-end mt-5">
            {{if languages.length > 1}}
              <a href="#" class="btn btn-xs btn-outline-secondary me-3" data-trigger="copy">Copia italiano in tutte le lingue</a>
            {{/if}}
            <a href="#" class="btn btn-xs btn-success" data-trigger="save">Salva modifiche</a>
          </div>
        </div>
        {{/for}}
      {{/for}}
    </div>
  </script>

  <script id="tpl-test" type="text/x-jsrender">
    <p><strong>Query:</strong> <code>{{>query}}</code></p>
    <p><strong>Risultati:</strong> {{:totalCount}}</p>
    {{for searchHits}}
    <a href="{{:~i18n(extradata, 'urlAlias')}}" target="_blank" class="list-group-item list-group-item-action" aria-current="true">
    <div>
      <h5 class="mb-1">{{:~i18n(metadata.name)}}</h5>
      <small class="d-block">{{:~i18n(metadata.classDefinition.name)}}</small>
    </div>
  </a>
    {{/for}}
  </script>

  <script>
    $(document).ready(function (){
      $.views.helpers($.opendataTools.helpers);
      let objectContainer = $('#object-list')
      let queryContainer = $('#query-list')
      let spinner = $('#spinner')
      let testModal = $('#test-modal')
      let testContent = $('#test-content')
      let searchInput = $('input#object-search').hide()
      let filterToggle = $('#filterToggle').hide()
      filterToggle.find('input').on('change', function (){
        loadStaticQueries()
      })
      let errorToggle = $('#errorToggle').hide()
      errorToggle.find('input').on('change', function (){
        loadStaticQueries()
      })

      let contentSearch = function(url, cb, context){
        $.ajax({
          type: "GET",
          url: url,
          contentType: "application/json; charset=utf-8",
          dataType: "json",
          success: function(response) {
            if( 'error_message' in response )
              alert(response.error_message);
            else {
              if ($.isFunction(cb)) {
                cb.call(context, response);
              }
              testContent.html($($.templates('#tpl-test').render(response)))
              testModal.modal('show')
            }
          },
          error: function(data){
            var error = data.responseJSON;
            alert(error.error_message);
          }
        });
      };

      let loadStaticQueries = function (){
        objectContainer.html('')
        queryContainer.html('')
        spinner.show()
        let withField = filterToggle.find('input').is(':checked') ? 1 : 0
        let withError = errorToggle.find('input').is(':checked') ? 1 : 0
        $.get('/opendata/check_queries?load=1&withField='+withField+'&withError='+withError, function (response){
          spinner.hide()
          searchInput.show()
          filterToggle.show()
          errorToggle.show()
          let count = response.length
          $('h1.h3 span').text(' ('+count+')')
          $.each(response, function(){
            $($.templates('#tpl-object').render(this))
              .appendTo(objectContainer);
            let query = $($.templates('#tpl-query').render(this))
            query.find('[data-trigger="test"]').on('click', function (e){
              let url = $(this).data('url')
              let q = $(this).parent().parent().find('textarea').val()
              contentSearch(url+'/'+q)
              e.preventDefault()
            })

            query.find('[data-trigger="optimize"]').on('click', function (e){
              let textarea = $(this).parent().parent().find('textarea')
              $.ajax({
                type: "GET",
                url: '/opendata/check_queries?optimize='+textarea.val(),
                contentType: "application/json; charset=utf-8",
                dataType: "json",
                success: function(response) {
                  textarea.val(response)
                },
                error: function(data){
                  var error = data.responseJSON;
                  alert(error.error_message);
                }
              });
              e.preventDefault()
            })

            query.find('[data-trigger="copy"]').on('click', function (e){
              let parent = $(this).parents('[data-query-group]')
              let itaField = parent.find('textarea[data-attribute_language="ita-IT"]')
              if (itaField.length > 0) {
                parent.find('textarea').val(itaField.val())
              }
              e.preventDefault()
            })

            query.find('[data-trigger="save"]').on('click', function (e){
              let self = $(this)
              let originalContent = self.html()
              self.html('<i aria-hidden="true" class="fa fa-circle-o-notch fa-spin fa-fw"></i>')
              let parent = self.parents('[data-query-group]')
              let payload = []
              parent.find('textarea').each(function (){
                let value = $(this).data()
                value.query = $(this).val()
                payload.push(value)
              })
              let tokenNode = document.getElementById('ezxform_token_js');
              if (tokenNode) {
                $.ajax({headers: {'X-CSRF-TOKEN': tokenNode.getAttribute('title')}})
              }
              $.post('/opendata/check_queries?save=1', {blocks: payload}, function (r){
                self.html(originalContent)
              }, 'json')
              e.preventDefault()
            })

            query.appendTo(queryContainer);
          })
          searchInput.quicksearch('ul#object-list li')
        })
      }

      loadStaticQueries();
    })
  </script>
{/literal}
<script src={"javascript/jquery.quicksearch.js"|ezdesign}></script>