var Admin = {
	global : {},
	
	init : function(){
		this.init_rich_textarea();
		this.init_lang_switcher();
		this.init_checkbox();
		this.init_select();
		this.init_menu();
		this.init_ajax_forms();
		this.init_custom_fields();
		
		$('body').on('hidden.bs.modal', '.modal', function () {
			$(this).removeData('bs.modal');
		});
		$('body').on('hidden.bs.modal', '#jalert', function () {
			$("#ok_but").unbind("click");
		});
	},
	
	init_rich_textarea : function()
	{
		tinymce.init({ 
			selector:'.rich_textarea' ,
			menubar: false,
		});
	},
	
	init_lang_switcher : function(){
		$(document).on("change" , "#lang_switcher", function(){
			var v = $(this).val();
			$("*[role=lang][data-lang!='" + v + "']").hide();
			$("*[role=lang][data-lang='" + v + "']").show();
		})
		$("#lang_switcher").trigger("change");
	},
	
	init_checkbox : function(){
		$("input[type=checkbox],input[type=radio]").iCheck({
			checkboxClass: 'icheckbox_square-green',
			radioClass: 'iradio_square-green'
		});
	},
	
	init_select : function(){
		$('.bs-select ').selectpicker();
	},
	
	init_menu : function()
	{
		$(document).on("click" , ".nav_menu li", function(){
			$(".nav_menu li.active").not($(this)).removeClass("active").find(".arr").removeClass("fa-caret-up").addClass("fa-caret-down");
			if ($(this).find(".sub-menu"))
			{
				if ($(this).hasClass("active"))
				{
					$(this).removeClass("active");
					$(this).find(".arr").removeClass("fa-caret-up").addClass("fa-caret-down");
				}
				else
				{
					$(this).addClass("active");
					$(this).find(".arr").addClass("fa-caret-up").removeClass("fa-caret-down");
				}
			}
		})
	},
	
	init_ajax_forms : function()
	{
		$(document).on("submit" , ".ajax_form", function(){
			Admin.manual_submit($(this));
			
			return false;
		})
	},
	
	manual_submit : function(obj)
	{
		var adr = obj.attr("action");
		var callback = obj.attr("data-callback");
		
		$.post(adr, obj.serialize(), function(data){
			if (data.error)
				toastr.error(data.error)
			else
			{
				eval(callback);
				if (data.success)
					toastr.success(data.success);
				if (data.location)
					window.location = data.location;
			}
		},'json')
	},
	
	hide_modal: function(size)
	{
		if (size == "long")
			$('#ajax_modal_long').modal('hide')
		else
			$('#ajax_modal').modal('hide')
	},
	
	init_custom_fields : function()
	{
		$(document).on("change" , "#custom_field_type", function(){
			var v = $(this).val();
			var d = $(this).find("option:selected").attr("data-multilang");
			if (d == 1)
				$("#multilang_check").show();
			else
				$("#multilang_check").hide().find("input[value='0']").iCheck('check');
			
			$(".field_options[data-type!='" + v + "']").hide();
			$(".field_options[data-type='" + v + "']").show();
		})
		$("#custom_field_type").trigger("change");
	},
	
	jalert : function(msg , callback)
	{
		$('#jalert .modal-body p').html(msg)
		$('#jalert').modal('show');
		$('#jalert #ok_but').on('click', callback);
	},
	
	check_custom_field : function(event)
	{
		var is_multilang = $("#multilang_check input:checked").val();
		var was_multilang = $("#was_multilang").val();
		var has_multilang =  $("#custom_field_type").find("option:selected").attr("data-multilang");
		
		var is_type = $("#custom_field_type").val();
		var had_type = $("#had_type").val();
		
		var msg = '';
		
		if (had_type != is_type)
		{
			msg = 'You changed <b>custom field type</b>. You can lose some content related to this custom field.';
		}
		
		if (is_multilang != was_multilang && has_multilang)
		{
			msg += '<br />You changed <b>multilanguage</b> option. You can lose some content related to this custom field.';
		}
		
		if (msg.length)
		{
			msg += '<br /><b>Are you sure you want to continue ?</b>';
			Admin.jalert(msg, function(){
				$('#jalert').modal('hide')
				Admin.manual_submit($("#custom_field_form"));
				$("#was_multilang").val(is_multilang);
				$("#had_type").val(is_type);
			})
			event.stopPropagation();
			return false;
		}
		else		
			return true;
	},
	
	delete_field : function(obj)
	{
		var msg = "If you delete it, you will loose all the content from this custom field. <br />Are you sure you want to delete it ?";
		Admin.jalert(msg, function(){
			$('#jalert').modal('hide')
			window.location = obj.attr("href") ;
		})
		return false;
	},
	
	delete_group : function(obj)
	{
		var msg = "If you delete it, you will loose all the content from this custom field group. <br />Are you sure you want to delete it ?";
		Admin.jalert(msg, function(){
			$('#jalert').modal('hide')
			window.location = obj.attr("href") ;
		})
		return false;
	},
	
	delete_node : function(obj)
	{
		var msg = "If you will delete this node, you will loose all the node content . <br />Are you sure you want to delete it ?";
		Admin.jalert(msg, function(){
			$('#jalert').modal('hide')
			window.location = obj.attr("href") ;
		})
		return false;
	},
	
	delete_channel : function(obj)
	{
		var msg = "If you will delete this channel, you will loose all the content from it. <br />Are you sure you want to delete it ?";
		Admin.jalert(msg, function(){
			$('#jalert').modal('hide')
			window.location = obj.attr("href") ;
		})
		return false;
	}
}	

$(document).ready(function(){
	Admin.init();
})