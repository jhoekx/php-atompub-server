

( function (scope) {
    var nsresolver = function (prefix) {
        var list = {
            atom:"http://www.w3.org/2005/Atom",
            html:"http://www.w3.org/1999/xhtml",
            app:"http://www.w3.org/2007/app"
        };
        
        return list[prefix];
    };
    
    var Manager = {
        load: function (uri) {
            var xhr = new XMLHttpRequest();
            
            xhr.open("GET", uri);
            xhr.onload = function () {
                var contentType = xhr.getResponseHeader("Content-Type");
                
                if (contentType.indexOf("atom")!==-1) {
                    switch (xhr.responseXML.documentElement.localName) {
                        case "feed":
                            var feed = atomize(xhr.responseXML);
                            displayFeed(feed, uri);
                            break;
                        case "entry":
                            var entry = atomize(xhr.responseXML);
                            entry.etag = xhr.getResponseHeader("ETag");
                            displayEntry(entry, uri);
                            break;
                        case "service":
                            var service = atomize(xhr.responseXML);
                            displayService(service, uri);
                            break;
                    }
                } else {
                    window.location.href=uri;
                }
                
            };
            
            xhr.send("");
        },
        save: function () {
            // update values
            var entry = this.entry;
            
            // title
            var title = entry.title;
            title.textContent = document.getElementById("article-title").value;
            
            // author
            var author = entry.documentElement.selectSingleNode("atom:author", nsresolver);
            var name = entry.documentElement.selectSingleNode("atom:author/atom:name", nsresolver);
            var uri = entry.documentElement.selectSingleNode("atom:author/atom:uri", nsresolver);
            if (!author) {
                author = entry.createElementNS(nsresolver("atom"), "author");
                entry.documentElement.appendChild(author);
            }
            if (!name) {
                name = entry.createElementNS(nsresolver("atom"), "name");
                author.appendChild(name);
            }
            name.textContent = document.getElementById("sidebar-name").value;
            if (document.getElementById("sidebar-uri").value) {
                if (!uri) {
                    uri = entry.createElementNS(nsresolver("atom"), "uri");
                    author.appendChild(uri);
                }
                uri.textContent = document.getElementById("sidebar-uri").value;
            } else {
                if (uri) {
                    uri.parentNode.removeChild(uri);
                }
            }
            // summary
            var summary = entry.documentElement.selectSingleNode("atom:summary", nsresolver);
            if (document.getElementById("sidebar-summary").value) {
                if (!summary) {
                    summary = entry.createElementNS(nsresolver("atom"), "summary");
                    entry.documentElement.appendChild(summary);
                }
                summary.setAttribute("type", "text");
                summary.textContent = document.getElementById("sidebar-summary").value;
            } else {
                if (summary) {
                    summary.parentNode.removeChild(summary);
                }
            }
            // time
            var updated = entry.documentElement.selectSingleNode("atom:updated", nsresolver);
            var published = entry.documentElement.selectSingleNode("atom:published", nsresolver);
            if (!updated) {
                updated = entry.createElementNS(nsresolver("atom"), "updated");
                entry.documentElement.appendChild(updated);
            }
            updated.textContent = document.getElementById("sidebar-updated").value;
            if (document.getElementById("sidebar-published").value) {
                if (!published) {
                    published = entry.createElementNS(nsresolver("atom"), "published");
                    entry.documentElement.appendChild(published);
                }
                published.textContent = document.getElementById("sidebar-published").value;
            } else {
                if (published) {
                    published.parentNode.removeChild(published);
                }
            }
            
            // content
            var content = document.getElementById("content");
            if (Manager.editable) {
                if (content.firstElementChild.localName.toLowerCase()==="textarea") {
                    if (!content.firstElementChild.updateAtomContent()) {
                        return false;
                    }
                }
            }
            
            //alert(document.implementation.createLSSerializer().writeToString(entry));
            var xhr = new XMLHttpRequest();
            if (this.entryURI) {
                // PUT entry
                xhr.open("PUT", this.entryURI);
                if (entry.etag) {
                    xhr.setRequestHeader("if-match", entry.etag);
                }
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        alert(xhr.status + " " + xhr.statusText);
                        if (xhr.status>=200 && xhr.status<=300) {
                            Manager.load(Manager.entryURI);
                        }
                    }
                };
            } else {
                // POST to collection
                xhr.open("POST", this.collectionURI);
                xhr.setRequestHeader("Slug", entry.title.text);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === 4) {
                        alert(xhr.status + " " + xhr.statusText);
                        if (xhr.status>=200 && xhr.status<=300) {
                            Manager.load(xhr.getResponseHeader("Location"));
                        }
                    }
                };
            }
            xhr.setRequestHeader("Content-Type","application/atom+xml;type=entry");
            xhr.send(entry);
        },
        remove: function () {
            if (!this.entryURI) {
                this.clear();
                return;
            }
            
            var xhr = new XMLHttpRequest();
            xhr.open("DELETE", Manager.entryURI);
            xhr.onreadystatechange = function () {
                alert(xhr.status + " " + xhr.statusText);
                if (xhr.status>=200 && xhr.status<=300) {
                    Manager.clear();
                }
            };
            if (confirm("Are you sure you want to remove this article?")) {
                xhr.send();
            }
        },
        clear: function () {
            var entry = document.implementation.createDocument(nsresolver("atom"), "entry", null);
            
            var title = entry.createElementNS(nsresolver("atom"), "title");
            title.textContent = "Untitled";
            entry.documentElement.appendChild(title);
            
            var author = entry.createElementNS(nsresolver("atom"), "author");
            var name = entry.createElementNS(nsresolver("atom"), "name");
            author.appendChild(name);
            entry.documentElement.appendChild(author);
            
            var id = entry.createElementNS(nsresolver("atom"), "id");
            var now = new Date();
            id.textContent = "tag:"+document.domain+","+now.getFullYear()+"-"+(now.getMonth()+1)+"-"+now.getDate()+":"+parseInt(Math.random()*100000,10);
            entry.documentElement.appendChild(id);
            
            var updated = entry.createElementNS(nsresolver("atom"), "updated");
            updated.textContent = now.toAtomString();
            entry.documentElement.appendChild(updated);
            
            var content = entry.createElementNS(nsresolver("atom"), "content");
            content.setAttribute("type","xhtml");
            var div = entry.createElementNS(nsresolver("html"), "div");
            var p = entry.createElementNS(nsresolver("html"), "p");
            p.textContent = "Start writing here...";
            div.appendChild(p);
            content.appendChild(div);
            entry.documentElement.appendChild(content);
            
            document.getElementById("sidebar-summary").value = "";
            
            displayEntry(entry, null);
        },
        showMedia: function () {
            var list = document.getElementById("media-options");
            var collections = Manager.service.selectNodes("//app:collection", nsresolver);
            addCollectionsToList(collections, list);
            
            list.addEventListener("change", function () {
                var collection = collections[this.selectedIndex];
                var uri = collection.getAttributeNode("href").resolveURI(Manager.serviceURI);
                
                list.form.action = uri;
            }, false);
            
            list.form.action = collections[0].getAttributeNode("href").resolveURI(Manager.serviceURI);
        },
        service:null,
        serviceURI:null,
        collection:null,
        collectionURI:null,
        entry:null,
        entryURI:null
    };
    scope.Manager = Manager;
    
    function displayEntry(entry, uri) {
        
        Manager.entryURI = uri;
        Manager.entry = entry;
        
        document.getElementById("toolbar-edit").parentNode.removeClass("hidden");
        document.getElementById("toolbar-preview").parentNode.addClass("hidden");
        
        // title
        document.getElementById("article-title").value = entry.title.text;
        // author
        var name = entry.documentElement.selectSingleNode("atom:author/atom:name", nsresolver);
        var uri = entry.documentElement.selectSingleNode("atom:author/atom:uri", nsresolver);
        if (name) {
            document.getElementById("sidebar-name").value = name.textContent;
        } else {
            document.getElementById("sidebar-name").value = "";
        }
        if (uri) {
            document.getElementById("sidebar-uri").value = uri.textContent;
        } else {
            document.getElementById("sidebar-uri").value = "";
        }
        // summary
        var summary = entry.documentElement.selectSingleNode("atom:summary", nsresolver);
        if (summary) {
            document.getElementById("sidebar-summary").value = summary.text;
        } else {
            document.getElementById("sidebar-summary").value = "";
        }
        // time
        var updated = entry.documentElement.selectSingleNode("atom:updated", nsresolver);
        var published = entry.documentElement.selectSingleNode("atom:published", nsresolver);
        if (updated) {
            document.getElementById("sidebar-updated").value = Date.fromAtomString(updated.textContent).toAtomString();
        } else {
            document.getElementById("sidebar-updated").value = "";
        }
        if (published) {
            document.getElementById("sidebar-published").value = Date.fromAtomString(published.textContent).toAtomString();
        } else {
            var pub = document.getElementById("sidebar-published");
            var npub = document.createElement("input");
            npub.type = "datetime";
            pub.parentNode.replaceChild(npub, pub);
            npub.id = "sidebar-published";
        }
        
        // content
        var content = entry.documentElement.selectSingleNode("atom:content", nsresolver);
        var editable = document.getElementById("content").addAtomContent(content);
        if (editable) {
            Manager.editable = true;
        } else {
            Manager.editable = false;
        }
    }
    
    function displayService(doc, uri) {
        Manager.service = doc;
        Manager.serviceURI = uri;
        
        var list = document.getElementById("open-options");
        var collections = doc.selectNodes("//app:collection", nsresolver);
        addCollectionsToList(collections, list);
        
        list.addEventListener("change", function () {
            var collection = collections[this.selectedIndex];
            Manager.load( collection.getAttributeNode("href").resolveURI(uri) );
        }, false);
        
        Manager.load( collections[0].getAttributeNode("href").resolveURI(uri) );
        Manager.collectionURI = collections[0].getAttributeNode("href").resolveURI(uri);
    }
    
    function displayFeed(feed, uri) {
        Manager.collection = feed;
        
        var list = document.selectSingleNode("id('open')/ul");
        list.empty();
        var entries = feed.documentElement.selectNodes("atom:entry", nsresolver);
        for (var i=0, entry; entry=entries[i]; i++) {
            var li = document.createElement("li");
            var a = document.createElement("a");
            a.href = entry.uri;
            a.appendChild( document.createTextNode(entry.title.text) );
            li.appendChild(a);
            list.appendChild(li);
        }
        
        list.addEventListener("click", function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            
            Manager.collectionURI = uri;
            
            if (ev.target.href) {
                Manager.load(ev.target.href);
                document.getElementById("open").addClass("hidden");
            }
        }, false);
    }
    
    function addCollectionsToList(collections, list) {
        list.empty();
        
        for (var i=0, collection; collection=collections[i]; i++) {
            var option = document.createElement("option");
            var title = collection.selectSingleNode("atom:title", nsresolver).text;
            option.appendChild( document.createTextNode(title) );
            
            list.appendChild(option);
        }
    }
})(window);