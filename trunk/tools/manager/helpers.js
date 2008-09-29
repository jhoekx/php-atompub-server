String.prototype.trim = function()
{
    return this.replace( /^\s+|\s+$/g, '' );
}

Date.prototype.toAtomString = function() {
    // 2008-02-26T09:51:42Z
    var year = this.getUTCFullYear();
    var month= this.getUTCMonth()+1;
    var date = this.getUTCDate();
    
    var hour = this.getUTCHours();
    var mins = this.getUTCMinutes();
    var secs = this.getUTCSeconds();
    
    if (month<10) {
        month = "0"+month;
    }
    if (date<10) {
        date = "0"+date;
    }
    if (hour<10) {
        hour = "0"+hour;
    }
    if (mins<10) {
        mins = "0"+mins;
    }
    if (secs<10) {
        secs = "0"+secs;
    }
    //opera.postError("This date: "+this);
    //opera.postError("To Date: "+""+year+"-"+month+"-"+date+"T"+hour+":"+mins+":"+secs+"Z");
    return ""+year+"-"+month+"-"+date+"T"+hour+":"+mins+":"+secs+"Z";
};

Date.fromAtomString = function (str) {
    //opera.postError("From Date: "+str);
    if (!str) {
        return "";
    }
    try {
    var parts = str.split("T"); // date T time
    
    // date
    var date = parts[0].split("-");
    var year = date[0];
    var month= parseInt(date[1],10)-1;
    var day  = date[2];
    
    // time
    if (parts[1].indexOf("Z") == -1) {
        // with TZ
        if (parts[1].indexOf("+") == -1) {
            // -
            // time - tz
            var time = parts[1].split("-");
            
            var ltime = time[0].split(":");
            var hours = ltime[0];
            var mins  = ltime[1];
            var secs = ltime[2] ? ltime[2] : 0;
            
            // timezone
            var tz = parseInt(time[1].split(":")[0], 10);
            
            var t= new Date( Date.UTC(year, month, day, hours, mins, secs) );
            
            //opera.postError("TZ- "+new Date( t.getTime() + parseInt(time[2].substr(1),10)*60*1000 ));
            return new Date( t.getTime() + tz*60*60*1000 );
            
        } else {
            // time + tz
            var time = parts[1].split("+");
            
            // time
            var ltime = time[0].split(":");
            var hours = ltime[0];
            var mins  = ltime[1];
            var secs = ltime[2] ? ltime[2] : 0;
            
            // timezone
            var tz = parseInt(time[1].split(":")[0], 10);
            
            var t = new Date( Date.UTC(year, month, day, hours, mins, secs) );

            //opera.postError("t: "+t);
            //opera.postError("o: "+time[2].substr(3));
        //opera.postError("TZ+ "+new Date( t.getTime() + parseInt(time[2].substr(3),10)*60*60*1000 ));
            return new Date( t.getTime() - tz*60*60*1000 );
        }
        
    } else {
        // UTC
        var time = parts[1].substring(0,parts[1].length-2).split(":");
        var hours = time[0];
        var mins  = time[1];
        var secs = time[2] ? time[2] : 0;
        
        // opera.postError("UTC "+new Date(Date.UTC(year, month, day, hours, mins, secs)));
        return new Date(Date.UTC(year, month, day, hours, mins, secs));
    }
    
    } catch (err) {
        opera.postError(err);
    }
};

/**
 *  Prototype extension to sanitize an element
 */
Element.prototype.sanitize = function () {
    var elements = {
        "http://www.w3.org/1999/xhtml": [
  "a", "abbr", "acronym", "address", "area", "b", "bdo", "big", "blockquote",
  "br", "button", "caption", "center", "cite", "code", "col", "colgroup", "dd",
  "del", "dfn", "dir", "div", "dl", "dt", "em", "fieldset", "font", "form",
  "h1", "h2", "h3", "h4", "h5", "h6", "hr", "i", "img", "input", "ins", "kbd",
  "label", "legend", "li", "map", "menu", "ol", "optgroup", "option", "p",
  "pre", "q", "s", "samp", "select", "small", "span", "strike", "strong",
  "sub", "sup", "table", "tbody", "td", "textarea", "tfoot", "th", "thead",
  "tr", "tt", "u", "ul", "var", "wbr", "figure" ],
        "http://www.w3.org/1998/Math/MathML": [
  "maction", "math", "merror", "mfrac", "mi", "mmultiscripts", "mn", "mo",
  "mover", "mpadded", "mphantom", "mprescripts", "mroot", "mrow", "mspace",
  "msqrt", "mstyle", "msub", "msubsup", "msup", "mtable", "mtd", "mtext",
  "mtr", "munder", "munderover", "none" ],
        "http://www.w3.org/2000/svg": [
  "a", "animate", "animateColor", "animateMotion", "animateTransform",
  "circle", "defs", "desc", "ellipse", "font-face", "font-face-name",
  "font-face-src", "g", "glyph", "hkern", "image", "line", "linearGradient",
  "marker", "metadata", "missing-glyph", "mpath", "path", "polygon",
  "polyline", "radialGradient", "rect", "set", "stop", "svg", "switch", "text",
  "title", "tspan", "use"]
    };
    var attributes = [
  "abbr", "accept", "accept-charset", "accesskey", "action", "align", "alt",
  "axis", "border", "cellpadding", "cellspacing", "char", "charoff", "charset",
  "checked", "cite", "class", "clear", "color", "cols", "colspan", "compact",
  "coords", "datetime", "dir", "disabled", "enctype", "for", "frame",
  "headers", "height", "href", "hreflang", "hspace", "id", "ismap", "label",
  "lang", "longdesc", "maxlength", "media", "method", "multiple", "name",
  "nohref", "noshade", "nowrap", "prompt", "readonly", "rel", "rev", "rows",
  "rowspan", "rules", "scope", "selected", "shape", "size", "span", "src",
  "start", "style", "summary", "tabindex", "target", "title", "type", "usemap",
  "valign", "value", "vspace", "width", "xml:lang", "xmlns",
  
  "actiontype", "align", "columnalign", "columnalign", "columnalign",
  "columnlines", "columnspacing", "columnspan", "depth", "display",
  "displaystyle", "equalcolumns", "equalrows", "fence", "fontstyle",
  "fontweight", "frame", "height", "linethickness", "lspace", "mathbackground",
  "mathcolor", "mathvariant", "mathvariant", "maxsize", "minsize", "other",
  "rowalign", "rowalign", "rowalign", "rowlines", "rowspacing", "rowspan",
  "rspace", "scriptlevel", "selection", "separator", "stretchy", "width",
  "width", "xlink:href", "xlink:show", "xlink:type", "xmlns", "xmlns:xlink",
  
  "accent-height", "accumulate", "additive", "alphabetic", "arabic-form",
  "ascent", "attributeName", "attributeType", "baseProfile", "bbox", "begin",
  "by", "calcMode", "cap-height", "class", "color", "color-rendering",
  "content", "cx", "cy", "d", "descent", "display", "dur", "dx", "dy", "end",
  "fill", "fill-rule", "font-family", "font-size", "font-stretch",
  "font-style", "font-variant", "font-weight", "from", "fx", "fy", "g1", "g2",
  "glyph-name", "gradientUnits", "hanging", "height", "horiz-adv-x",
  "horiz-origin-x", "id", "ideographic", "k", "keyPoints", "keySplines",
  "keyTimes", "lang", "marker-end", "marker-mid", "marker-start",
  "markerHeight", "markerUnits", "markerWidth", "mathematical", "max", "min",
  "name", "offset", "opacity", "orient", "origin", "overline-position",
  "overline-thickness", "panose-1", "path", "pathLength", "points",
  "preserveAspectRatio", "r", "refX", "refY", "repeatCount", "repeatDur",
  "requiredExtensions", "requiredFeatures", "restart", "rotate", "rx", "ry",
  "slope", "stemh", "stemv", "stop-color", "stop-opacity",
  "strikethrough-position", "strikethrough-thickness", "stroke",
  "stroke-dasharray", "stroke-dashoffset", "stroke-linecap", "stroke-linejoin",
  "stroke-miterlimit", "stroke-opacity", "stroke-width", "systemLanguage",
  "target", "text-anchor", "to", "transform", "type", "u1", "u2",
  "underline-position", "underline-thickness", "unicode", "unicode-range",
  "units-per-em", "values", "version", "viewBox", "visibility", "width",
  "widths", "x", "x-height", "x1", "x2", "xlink:actuate", "xlink:arcrole",
  "xlink:href", "xlink:role", "xlink:show", "xlink:title", "xlink:type",
  "xml:base", "xml:lang", "xml:space", "xmlns", "xmlns:xlink", "y", "y1", "y2",
  "zoomAndPan"
];
    
    var uriAttributes = [
  "action", "cite", "href", "longdesc", "src", "xlink:href", "xml:base"
];

    var uriSchemes = [
  "afs", "aim", "callto", "ed2k", "feed", "ftp", "gopher", "http", "https",
  "irc", "mailto", "news", "nntp", "rsync", "rtsp", "sftp", "ssh", "tag",
  "tel", "telnet", "urn", "webcal", "wtai", "xmpp", "opera"
];

    var sanitize = function (element) {
        var removeElement = function (element) {
            if (window.opera) {
                if (element.namespaceURI) {
                    opera.postError("Sanitizer : Element {"+element.namespaceURI+"}"+element.localName+" removed.");
                } else {
                    opera.postError("Sanitizer : Element "+element.localName+" removed.");
                }
            }
            element.parentNode.removeChild(element);
        };
        
        // check current element
        var ns = element.namespaceURI;
        var name = element.localName;
        if (ns==null) { // HTML
            ns = "http://www.w3.org/1999/xhtml";
            name = name.toLowerCase();
        }

        if ( !elements[ns] || (elements[ns].indexOf(name)==-1) ) {
            removeElement(element);
            return;
        }

        // check childnodes
        for (var i=0; i<element.childNodes.length; i++) {
            var childNode = element.childNodes[i];
            
            if (childNode.nodeType == Node.ELEMENT_NODE) {
                arguments.callee(childNode);
            }
        }
        // check attributes
        for (var i=0; i<element.attributes.length; i++) {
            var attribute = element.attributes[i];
            if (attributes.indexOf(attribute.nodeName) == -1) {
                if (attribute.nodeName.indexOf("xmlns")!==0) {
                    attribute.ownerElement.removeAttributeNode(attribute);
                    if (window.opera) {
                        opera.postError("Sanitizer : Attribute "+attribute.nodeName+" removed.");
                    }
                }
            }
            
            if (uriAttributes.indexOf(attribute.nodeName) != -1) {
                // check URI
                var uri = parseURI( resolveURI(attribute, document.location.href) );
                if ( uriSchemes.indexOf(uri.scheme) == -1) {
                    attribute.ownerElement.removeAttributeNode(attribute);
                    if (window.opera) {
                        opera.postError("Sanitizer : Unsafe URI "+attribute.nodeValue+" removed.");
                    }
                }
            }
        }
    };
    
    sanitize(this);
};

/**
 * Prototype extension to convert all HTML childnodes into XHTML
 */
Element.prototype.toXHTML = function () {

    // copy element
    if ( this.namespaceURI===null) {
        var element = this.ownerDocument.createElementNS(
                    "http://www.w3.org/1999/xhtml", this.localName.toLowerCase());
    } else {
        var element = this.ownerDocument.createElementNS(this.namespaceURI, this.localName);
    }

    // childnodes
    for (var i=this.childNodes.length-1, child; child=this.childNodes[i]; i--) {
        switch (child.nodeType) { 
            case Node.ELEMENT_NODE:
                element.insertBefore(child, element.firstChild);
                child.toXHTML();
                break;
            case Node.TEXT_NODE:
            case Node.CDATA_SECTION_NODE:
                element.insertBefore(child, element.firstChild);
                break;
            default:
                break;
        }
    }
    
    // attributes
    for (var i=0, attr; attr=this.attributes[i]; i++) {
        element.setAttribute(attr.nodeName, attr.nodeValue);
    }
    
    if (this.parentNode) {
        this.parentNode.replaceChild(element, this);
    }
};

Element.prototype.empty = function () {
    while (this.firstChild) {
        this.removeChild(this.firstChild);
    }
};
Element.prototype.appendChildren = function (children) {
    while (children[0]) { // Nodelists are live
        this.appendChild(children[0]);
    };
};

/**
 *  Prototype extension to simplify adding and sanitizing markup to an element.
 *  arguments = some strings containing the HTML markup to sanitize and append
 */
HTMLElement.prototype.appendMarkup = function()
{
    var dummy = document.createElement('div');
    dummy.innerHTML = arguments.join('');

    dummy.sanitize();

    //  append DOM tree of dummy in this
    var i = 0, node;
    while( (this.textContent.length<256) && (node=dummy.childNodes[i]) )
    {
        this.appendChild( node.cloneNode( true ) );
        i++;
    }
    if( node )
    {
        this.appendChild( document.createTextNode( '...' ) );
    }

    return this;
};

HTMLElement.prototype.supportsAtomContent = function (content) {
    if (!content) {
        return false;
    }
    switch (content.getAttribute("type")) {
        case "xhtml":
        case "html":
        case "text":
        case "":
        case null:
        case "image/jpeg":
        case "image/gif":
        case "image/png":
            return true;
            break;
        default:
            return false;
            break;
    }
    return false;
};

HTMLElement.prototype.addAtomContent = function (content) {
    if (!content) {
        return false;
    }
    this.removeClass("atompre");
    this.atomcontent = content;

    var document = this.ownerDocument;
    switch (content.getAttribute("type")) {
        case "xhtml":
            var div = content.firstElementChild;
            div.sanitize();
            
            div.setAttribute("data-test","");
            var base = resolveURI( div.getAttributeNode("data-test"), document.location.href);
            this.setAttributeNS("http://www.w3.org/XML/1998/namespace","xml:base", base);
            div.removeAttribute("data-test");
            
            this.empty();
            
            var ndiv = document.importNode(div, true);
            this.appendChildren( ndiv.childNodes );
    
            return true;
            break;
        case "html":
            var div = document.createElement("div");
            div.innerHTML = content.textContent;
            
            content.setAttribute("data-test","");
            var base = resolveURI( content.getAttributeNode("data-test"), document.location.href);
            this.setAttributeNS("http://www.w3.org/XML/1998/namespace","xml:base", base);
            div.setAttributeNS("http://www.w3.org/XML/1998/namespace","xml:base", base);
            content.removeAttribute("data-test");
            
            div.sanitize();
            
            this.innerHTML= div.innerHTML;
            return true;
            break;
        case "text":
        case "":
        case null:
            this.empty();
            
            this.textContent = content.textContent;
            this.addClass("atompre");

            return true;
            break;
        case "image/jpeg":
        case "image/gif":
        case "image/png":
            this.empty();
            var img = document.createElement("img");
            if (content.hasAttribute("src")) {
                img.src = resolveURI(content.getAttributeNode("src"), document.location.href)
            } else {
                img.src = "data:"+content.getAttribute("type")+";base64,"+content.textContent.trim();
            }
            this.appendChild(img);
            return false;
            break;
    }
};

HTMLElement.prototype.prepareForeignContent = function () {
    var list = [];

    var svgs = this.getElementsByTagNameNS("http://www.w3.org/2000/svg","svg");
    for (var i=0; i<svgs.length; i++) {
        
        var svg = svgs[i].cloneNode(true);
        
        var div = this.ownerDocument.createElement("div");
        div.contentEditable = false;
        div.className = "foreignContent";
        
        list.push({"div":div, "el":svg});
        
        svgs[i].parentNode.replaceChild(div, svgs[i]);
        div.appendChild(svg.cloneNode(true));
    }
    
    this.holdForeignContent = function () {
        for (var i=0; i<list.length; i++) {
            if ( list[i].div ) {
                var el = list[i].el.cloneNode(true);
                while (list[i].div.firstChild) {
                    list[i].div.removeChild(list[i].div.firstChild);
                }
                list[i].div.appendChild(el);
            }
        }
    };
};

HTMLElement.prototype.restoreForeignContent = function () {
    var el, els = this.getElementsByClassName("foreignContent");
    while ( el=els[0] ) {
        var parent = el.parentNode;
        
        var child;
        while ( child=el.firstChild ) {
            parent.insertBefore(child, el);
        }
        parent.removeChild(el);
    }
};

HTMLTextAreaElement.prototype.addAtomContent = function (atomcontent) {
    this.atomcontent = atomcontent;
    
    switch (atomcontent.getAttribute("type")) {
        case "xhtml":
            this.value = document.implementation.createLSSerializer().writeToString(atomcontent.firstElementChild);
            break;
        case "html":
            this.value = atomcontent.textContent;
            break;
        case "text":
        case "":
        case null:
            this.value = atomcontent.textContent;
            break;
        default:
            return;
            break;
    }
};

HTMLTextAreaElement.prototype.updateAtomContent = function () {
    switch (this.atomcontent.getAttribute("type")) {
        case "xhtml":
            var div = this.atomcontent.firstElementChild;
        
            var lsp = document.implementation.createLSParser(DOMImplementationLS.MODE_SYNCHRONOUS, null);
            try {
                var doc = lsp.parse({stringData:this.value});
            } catch (err) {
                alert("Not Well-Formed XML.");
                return false;
            }
            var ndiv = div.ownerDocument.importNode(doc.documentElement, true);
            
            div.parentNode.replaceChild(ndiv, div);

            break;
        case "html":
            var div = document.createElement("div");
            div.innerHTML = this.value;
            this.atomcontent.textContent = div.innerHTML;
            break;
        case "text":
        case "":
        case null:
            this.atomcontent.textContent = this.value;
            break;
    }
    
    return true;
};

HTMLTextAreaElement.prototype.wysiwygize = function () {
    var div = document.createElement("div");
    
    div.addAtomContent(this.atomcontent);
    
    div.contentEditable = "true";

    this.parentNode.insertBefore(div, this);

    this.addClass("hidden");
};

HTMLElement.prototype.hasClass = function (classname) {
    var class = this.getAttribute("class");
    if (!class) {
        return false;
    }
    var re = new RegExp("\\b"+classname+"\\b");
    if (re.test(class)) {
        return true;
    } else {
        return false;
    }
};
HTMLElement.prototype.addClass = function (classname) {
    if (!this.hasClass(classname)) {
        if (this.getAttribute("class")) {
            this.setAttribute("class", this.getAttribute("class")+" "+classname);
        } else {
            this.setAttribute("class", classname);
        }
    }
};
HTMLElement.prototype.removeClass = function (classname) {
    var re = new RegExp("\\b"+classname+"\\b");
    if ( this.hasAttribute("class") ) {
        this.setAttribute("class", this.getAttribute("class").replace(re,""));
    }
};

Attr.prototype.resolveURI = function (base) {
    base = base||document.location.href;
    return resolveURI(this, base)
};