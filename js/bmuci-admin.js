jQuery(function($){		$('input[name="bmuci_import_type"]').click(function(){		if($(this).is(':checked')){			var val = $(this).val();			$('.import-options:not(.'+val+'-options)').hide();			$('.import-options.'+val+'-options').show();		}	});		$('input[name="bmuci_import_type"]:checked').click();		$('input[name="bmuci_schedule"]').click(function(){		if($(this).is(':checked')){			var val = $(this).val();			if(val == 'datetime'){				$('.datetime_input').show();			} else {				$('.datetime_input').hide();			}		}	});	$('input[name="bmuci_schedule"]:checked').click();		$('input[name="bmuci_last_import"]').click(function(){		if($('#bmuci-match').length <= 0){			if($(this).is(':checked')){				$('.import-options').addClass('hidden');				$('.import-type').hide();			} else {				$('.import-options').removeClass('hidden');				$('.import-type').show();			}		}	});		$('select[name="bmuci_post_type"]').change(function(){		if($(this).val() == '__custom__'){			$('.alt-cpt-field').show();		} else {			$('.alt-cpt-field').hide();		}	}).change();		$('select[name^="db_field"]:not(select[name="db_field[custom_col]"])').change(function(){		var $input = $(this).next('input:first');		if($(this).val() == '__custom__'){			$input.show();			$input.removeAttr('disabled');		} else {			$input.attr('disabled','disabled');			$input.hide();		}				if($(this).val() == '__taxonomy__'){			$(this).attr('name',$(this).attr('name')+'[]');			var $select = $(this).siblings('.taxonomy-select');			if(!$select.length){				$select = $('<select />').attr('name', $(this).attr('name')).addClass('taxonomy-select');				$(this).after($select);			}			$.ajax({				type: 'post',				url: '/wp-admin/admin-ajax.php',				data: {					action: 'bmuci_fetch_taxonomy_options'				},				success: function(response){					$select.html(response);				}			});		} else {			$(this).siblings('.taxonomy-select').hide();		}	}).change();			$('select.custom_concat').live('change',function(){		if($(this).val() == '__string__'){			$(this).siblings('.concat_string:first').show();		} else {			$(this).siblings('.concat_string:first').hide();		}	});		$('select#custom_method').change(function(){		var method = $(this).val().toLowerCase();		$('.custom-column-method:not(.method-'+method+')').hide();		$('.custom-column-method.method-'+method+'').show().find('select').change();	}).change();		$('select[name="db_field[custom_col]"]').change(function(){		if($(this).val()){			$('.other-steps').show();		} else {			$('.other-steps').hide();		}	}).change();		$('#add-custom-column').click(function(){		var $col = $('select[name="db_field[custom_col]"]');		var $method = $('select#custom_method');		var $concats = $('select.custom_concat');		var $value = $('#custom_value');				var value = $('#custom_value').val();				if($method.val() == 'CONCAT'){			value = '';			$.each($concats, function(i, el){				var $select = $(el);				if($select.val() == '__string__'){					value += $('.concat_string:eq('+i+')').val();				} else {					value += '{'+$select.val()+'}';				}			});		}				$('#custom-columns').show();				var $td = $('<td />');		var $input = $('<input />').attr('type','hidden');		var $delete = $('<a />').attr('href','#delete').attr('title','Delete').addClass('delete').text('Delete');				var $tr = $('<tr />');		var $first = $td.clone().append($col.val());			$first.append($input.clone().attr('name','custom_columns[col][]').val($col.val()));			$first.append($input.clone().attr('name','custom_columns[method][]').val($method.val()));			$first.append($input.clone().attr('name','custom_columns[value][]').val(value));		$tr.append($first);		$tr.append($td.clone().html($method.val()));		$tr.append($td.clone().html(value));		$tr.append($td.clone().append($delete));				$('#custom-columns tbody').append($tr);		$('#custom-columns tbody tr:odd').addClass('alternate');				$('.custom-column-create').find('select,input[type="text"]').val('');		$('.custom-column-create').find('select').change();		$('.custom-column-create .concat_wrap:not(:first)').remove();				return false;	});		$('.custom-column-method a.plus').live('click',function(){		var $wrap = $('.concat_wrap:first').clone();		$wrap.find('select,input[type="text"]').val('');		$('.concat_wrap:last').after($wrap);		return false;	});		$('.concat_wrap a.minus').live('click',function(){		if($('.concat_wrap').length > 1){			$(this).parents('.concat_wrap').remove();		}		return false;	});		$('#custom-columns a.delete').live('click', function(){		if(confirm('Are you sure you want to remove this custom column?')){			$(this).parents('tr').remove();			if($('#custom-columns table tbody tr').length <= 0){				$('#custom-columns').hide();			}		}		return false;	});		$('#bmuci-upload,#bmuci-match').submit(function(){		var message = 'Please wait...';				var id = $(this).attr('id');		if(id == 'bmuci-upload'){			message = 'Please wait while the file uploads...';		}				var $wait = $('<div />').addClass('wait').html(message);		$('.button-primary', $(this))			.css('visibility','hidden')			.css('width','0')			.css('height','0')			.css('position','absolute')			.after($wait);	});			$('input[name="bmuci_unique"],input[name="bmuci_id"]').click(function(){		var previous = $(this).attr('previous');		if(previous && previousElem === this){			$(this).prop('checked', false);		}		previousElem = this;		$(this).attr('previous', $(this).prop('checked'));	});		$("#filechooser").change(function() {		 // get the file name, possibly with path (depends on browser)        var filename = $("#filechooser").val();        // Use a regular expression to trim everything before final dot        var extension = filename.replace(/^.*\./, '');        // If there is no dot anywhere in filename, we would have extension == filename,        if (extension == filename) {            extension = '';        } else {            // if there is an extension, we convert to lower case            extension = extension.toLowerCase();        }        if( extension != "csv" ) {            $("#upload_error").html("<div class=\"error\">This is not a valid file type. You must select a CSV file.</div>")        }        else {        	$("#upload_error").empty();        	        }	});});