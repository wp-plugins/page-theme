/*
 fcbkListSelection 1.10
 - Jquery version required: 1.2.x, 1.3.x, 1.4.x
 
 Changelog:
 - 1.1: added preselected items
 - 1.0: project started
 */
/* Coded by: emposha <admin@emposha.com> */
/* Copyright: Emposha.com <http://www.emposha.com/> - Distributed under MIT - Keep this message! */
/*
 * elem - ul element id or object
 * width - width of ul
 * height - height of each element
 * row - number of items in row
 * callback - function to invoke upon selection
 */
jQuery.fcbkListSelection = function(elem, width, height, row, callback){

    //get content of tabs
    var getContent = function(elem, tab){
        switch (tab) {
            case "all":
                elem.children("li").show();
                break;
                
            case "selected":
                elem.children("li:not([addedid])").hide();
                elem.children("li[addedid]").show();
                break;
                
            case "unselected":
                elem.children("li[addedid]").hide();
                elem.children("li:not([addedid])").show();
                break;
        }
    }
    
    var hiddenCheck = function(obj){
        switch (curTab()) {
            case "all":
                elem.children("li").show();
                break;
                
            case "selected":
                elem.children("li:not([addedid])").hide();
                elem.children("li[addedid]").show();
                break;
                
            case "unselected":
                elem.children("li[addedid]").hide();
                elem.children("li:not([addedid])").show();
                break;
        }
    }
    
    //add to selected items function
    var addToSelected = function(obj){
        if (obj.hasClass("itemselected")) {
            jQuery("#view_selected_count").text(parseInt(jQuery("#view_selected_count").text(), 10) - 1);
            obj.parents("li").removeAttr("addedid");
            removeValue(obj);
        }
        else {
            jQuery("#view_selected_count").text(parseInt(jQuery("#view_selected_count").text(), 10) + 1);
            obj.parents("li").attr("addedid", "tester");
            addValue(obj);
		if(callback) {
			callback(obj.parent()[0].id);
		}
        }
// CFR        hiddenCheck(obj);
    }
    
    //bind onmouseover && click event on item
    var bindEventsOnItems = function(elem){
        jQuery.each(elem.children("li").children(".fcbklist_item"), function(i, obj){
            obj = jQuery(obj);
            if (obj.children("input[checked]").length != 0) {
                addToSelected(obj);
                obj.toggleClass("itemselected");
                obj.parents("li").toggleClass("liselected");
            }
            obj.click(function(){
// CFR: Mod. by http://www.codethatmatters.com/2010/02/jquery-image-selector-facebook-style/
/*
                addToSelected(obj);
                obj.toggleClass("itemselected");
                obj.parents("li").toggleClass("liselected");
*/
	       //check if this is already selected. in that case it should be deselected
	       deselect_this = false;
	       if(parseInt(obj.attr("class").indexOf("selected")) > -1) deselect_this = true;
 
	       //deselecting all items
	       jQuery("li .fcbklist_item").each(function(x, obj2) {
		      obj2 = jQuery(obj2);
		      obj2.removeClass("itemselected"); 
		      obj2.parents("li").removeClass("liselected");
		      obj2.parents("li").removeAttr("addedid");
		      removeValue(obj2);
// CFR		      hiddenCheck(obj2);
	       });
 
	       //if the clicked item should be deselected, do so
	       if(!deselect_this)
	       {
		      addToSelected(obj);                            
		      obj.toggleClass("itemselected");
		      obj.parents("li").toggleClass("liselected");
	       }
            });
            obj.mouseover(function(){
                obj.addClass("itemover");
            });
            obj.mouseout(function(){
                jQuery(".itemover").removeClass("itemover");
            });
        });
    }
    
    //bind onclick event on filters
    var bindEventsOnTabs = function(elem){
        jQuery.each(jQuery("#selections li"), function(i, obj){
            obj = jQuery(obj);
            obj.click(function(){
                jQuery(".view_on").removeClass("view_on");
                obj.addClass("view_on");
                getContent(elem, obj.attr("id").replace("view_", ""));
            });
        });
    }
    
    //create control tabs
    var createTabs = function(elem, width){
        var html = '<div id="filters" style="width:' + (parseInt(width, 10) + 2) + 'px;">' +
        '<ul class="selections" id="selections"><li id="view_all" class="view_on">' +
        '<a onclick="return false;" href="#">View All</a></li><li id="view_selected" class="">' +
        '<a onclick="return false;" href="#">Selected (<strong id="view_selected_count">0</strong>)</a></li>' +
        '<li id="view_unselected" class=""><a onclick="return false;" href="#">Unselected</a></li></ul>' +
        '<div class="clearer"></div></div>';
        elem.before(html);
    }
    
    //wrap elements with div
    var wrapElements = function(elem, width, height, row){
        elem.children("li").wrapInner('<div class="fcbklist_item"></div>');
        jQuery(".fcbklist_item").css("height", height + "px");
        var newwidth = Math.ceil((parseInt(width, 10)) / parseInt(row, 10)) - 11;
// CFR        jQuery(".fcbklist_item").css("width", newwidth + "px");
        jQuery(".fcbklist_item").css("width", "300px");
    }
    
    var addValue = function(obj, value){
        //create input
        var inputid = elem.attr('id') + "_values";
        if (jQuery("#" + inputid).length == 0) {
            var input = document.createElement('input');
            jQuery(input).attr({
                'type': 'hidden',
                'name': inputid,
                'id': inputid,
                'value': ""
            });
            elem.after(input);
        }
        else {
            var input = jQuery("#" + inputid);
        }
        var randid = "rand_" + randomId();
        if (!value) {
            value = obj.find("[type=hidden]").val();
            obj.find("[type=hidden]").attr("randid", randid);
        }
        var jsdata = new data(randid, value);
        var stored = jsToString(jsdata, jQuery(input).val());
        jQuery(input).val(stored);
        return input;
    }
    
    var jsToString = function(jsdata, json){
        var string = "{";
        jQuery.each(jsdata, function(i, item){
            if (i) {
                string += "\"" + i + "\":\"" + item + "\",";
            }
        });
        try {
            eval("json = " + json + ";");
            jQuery.each(json, function(i, item){
                if (i && item) {
                    string += "\"" + i + "\":\"" + item + "\",";
                }
            });
        } 
        catch (e) {            
        }
        //remove last ,
        string = string.substr(0, (string.length - 1));
        string += "}"
        return string;
    }
    
    var data = function(id, value){
        try {
            eval("this." + id + " = value;");
        } 
        catch (e) {            
        }
    }
    
    var removeValue = function(obj){
        var randid = obj.find("[type=hidden]").attr("randid");
        var inputid = elem.attr('id') + "_values";
        if (jQuery("#" + inputid).length != 0) {
            try {
                eval("json = " + jQuery("#" + inputid).val() + ";");
                var string = "{";
                jQuery.each(json, function(i, item){
                    if (i && item && i != randid) {
                        string += "\"" + i + "\":\"" + item + "\",";
                    }
                });
                //remove last ,
                if (string.length > 2) {
                    string = string.substr(0, (string.length - 1));
                    string += "}"
                }
                else {
                    string = "";
                }
                jQuery("#" + inputid).val(string);
            } 
            catch (e) {                
            }
        }
    }
    
    var randomId = function(){
        var chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXTZabcdefghiklmnopqrstuvwxyz";
        var string_length = 32;
        var randomstring = '';
        for (var i = 0; i < string_length; i++) {
            var rnum = Math.floor(Math.random() * chars.length);
            randomstring += chars.substring(rnum, rnum + 1);
        }
        return randomstring;
    }
    
    var curTab = function(){
        return jQuery(".view_on").attr("id").replace("view_", "");
    }
    //main
    if (typeof(elem) != 'object') 
        elem = jQuery(elem);
// CFR elem.css("width", width + "px");
    
// CFR    createTabs(elem, width);
    wrapElements(elem, width, height, row);
    
// CFR    bindEventsOnTabs(elem);
    bindEventsOnItems(elem);
}
