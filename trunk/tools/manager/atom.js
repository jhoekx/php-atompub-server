/*
 * Atom DOM Classes
 * Exports "atomize()" into the given scope.
 */
( function (scope) {
    var nsresolver = function (prefix) {
        var list = {
            atom:"http://www.w3.org/2005/Atom",
            html:"http://www.w3.org/1999/xhtml",
            app:"http://www.w3.org/2007/app"
        };
            
        return list[prefix];
    };
    
    scope.atomize = function (document) {
        if (document.documentElement.localName==="feed") {
            document.prototype = AtomFeed.prototype;
            document.toString = function () {
                return "[object AtomFeed]";
            };

            for( var i=0, entry; entry=document.selectNodes("//atom:entry",nsresolver)[i]; i++ ) {
                entry.prototype = AtomEntry.prototype;
                entry.toString = function () {
                    return "[object AtomEntry]";
                };
            }
        }
        
        if (document.documentElement.localName==="entry") {
            document.prototype = AtomEntry.prototype;
            document.toString = function () {
                return "[object AtomEntry]";
            };
        }
        
        var atomTexts = document.selectNodes("//[atom:rights or atom:subtitle or atom:summary or atom:title ]",nsresolver);
        for ( var i=0, text; text=atomTexts[i]; i++) {
            text.prototype = AtomText.prototype;
            text.toString = function () {
                return "[object AtomText]";
            };
        }
        return document;
    }
    
    var AtomFeed = {};
    AtomFeed.prototype = XMLDocument.prototype;
    AtomFeed.prototype.__defineGetter__("title", function () {
        var title = this.documentElement.selectSingleNode("atom:title",nsresolver);
        return title;
    });
    
    var AtomEntry = {};
    AtomEntry.prototype = Element.prototype;
    AtomEntry.prototype.__defineGetter__("uri", function () {
        var link = this.selectSingleNode("atom:link[@rel='edit']", nsresolver);
        if (link) {
            return link.getAttributeNode("href").resolveURI();
        }
        return null;
    });
    AtomEntry.prototype.__defineGetter__("title", function () {
        var title = this.selectSingleNode("atom:title",nsresolver);
        return title;
    });
    AtomEntry.prototype.getAlternate = function (type) {
        type = type||"html"; // either text/html or application/xhtml+xml
        
        var links = this.selectNodes("atom:link", nsresolver);
        for (var i=0, link; link=links[i]; i++) {
            if ( link.hasAttribute("rel") ){
                if ( link.getAttribute("rel")=="alternate" ) {
                    if ( link.hasAttribute("type") ) {
                        if (link.getAttribute("type").indexOf(type)!=-1) {
                            break;
                        }
                    } else {
                        // Default to HTML
                        break;
                    }
                }
            } else {
                // defaults to alternate
                if ( link.hasAttribute("type") ) {
                    if (link.getAttribute("type").indexOf(type)!=-1) {
                        break;
                    }
                } else {
                    // Default to HTML
                    break;
                }
            }
            link = null;
        }
        return link;
    };
    AtomEntry.prototype.getAuthor = function (doc) {
        var doc = doc||document;
        
        var author = this.selectSingleNode("atom:author", nsresolver);
        if (!author) {
            author = this.selectSingleNode("atom:source/atom:author", nsresolver);
        }
        if (!author) {
            author = this.ownerDocument.documentElement.selectSingleNode("atom:author", nsresolver);
            if (!author) { // error!
                return;
            }
        }
        var name = author.selectSingleNode("atom:name", nsresolver);
        if (!name) {return;}
        
        var uri = author.selectSingleNode("atom:uri", nsresolver);
        var mail = author.selectSingleNode("atom:email", nsresolver);
        
        if (uri) {
            var a = doc.createElementNS(nsresolver("html"), "a");
            a.setAttribute("href", resolveURI(uri) );
            a.textContent = name.textContent;
        } else if (mail) {
            var a = doc.createElementNS(nsresolver("html"), "a");
            a.setAttribute("href", "mailto:"+mail.textContent );
            a.textContent = name.textContent;
        } else {
            var a = doc.createTextNode(name.textContent);
        }
        return a;
    };
    
    var AtomText = {};
    AtomText.prototype = Element.prototype;
    AtomText.prototype.__defineGetter__("text", function () {
        switch (this.getAttribute("type")) {
            case "xhtml":
                return this.textContent;
                break;
            case "html":
                var div = document.createElement("div");
                div.innerHTML = this.textContent;
                return div.textContent;
                break;
            default:
                return this.textContent;
                break;
        }
    });
    
} )(window);