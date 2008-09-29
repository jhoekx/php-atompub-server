
( function () {
    var toolbar = {
        "toolbar-new": function () {
            Manager.clear();
        },
        "toolbar-open": function () {
            Manager.load(Manager.collectionURI);
            document.getElementById("open").removeClass("hidden");
        },
        "toolbar-save": function () {
            document.getElementById("article-submit").click();
        },
        "toolbar-delete": function () {
            Manager.remove()
        },
        "toolbar-edit": function () {
            if (!Manager.editable) {
                alert("I can't edit this (yet)!");
                return;
            }
            var content = document.getElementById("content");
            var atomcontent = content.atomcontent;
            
            var textarea = document.createElement("textarea");
            textarea.rows = "20";
            textarea.cols = "80";
            
            textarea.addAtomContent(atomcontent);
            
            content.empty();
            content.appendChild(textarea);
            
            document.getElementById("toolbar-edit").parentNode.addClass("hidden");
            document.getElementById("toolbar-preview").parentNode.removeClass("hidden");
        },
        "toolbar-preview": function () {
            var content = document.getElementById("content");
            var atomcontent = content.atomcontent;
            var textarea = content.firstElementChild;
            
            if (!textarea.updateAtomContent()) {
                return;
            }
            content.addAtomContent(atomcontent);
            
            document.getElementById("toolbar-edit").parentNode.removeClass("hidden");
            document.getElementById("toolbar-preview").parentNode.addClass("hidden");
        },
        "toolbar-media": function () {
            document.getElementById("media").removeClass("hidden");
            Manager.showMedia();
        }
    };
    
    document.addEventListener("load", function () {
        
        document.getElementById("toolbar-preview").parentNode.addClass("hidden");
        
        document.getElementById("toolbar").addEventListener("click", function (ev) {
            ev.preventDefault();
            ev.stopPropagation();
            
            if (event.target.id) {
                if (toolbar[event.target.id]) {
                    toolbar[event.target.id]();
                }
            }
        }, false);
        
        document.getElementById("article-form").addEventListener("submit", function (ev) {
            ev.preventDefault();
            ev.stopPropagation();

            if (this.checkValidity()) {
                Manager.save();
            }
        }, false);
        
        document.getElementById("media-form").addEventListener("submit", function (ev) {
            
            var title = this.elements["media-title"].value;
            var summary = this.elements["media-summary"].value;
            var author = this.elements["media-author"].value;
            var filename = this.elements["file"].value;
            
            if (title||summary||author) {
                var entry = document.implementation.createDocument("http://www.w3.org/2005/Atom", "entry", null);
                
                var titleEl = entry.createElementNS("http://www.w3.org/2005/Atom", "title");
                titleEl.textContent = title||"Untitled";
                entry.documentElement.appendChild(titleEl);
                
                var authorEl = entry.createElementNS("http://www.w3.org/2005/Atom", "author");
                var name = entry.createElementNS("http://www.w3.org/2005/Atom", "name");
                name.textContent = author||"Anonymous";
                authorEl.appendChild(name);
                entry.documentElement.appendChild(authorEl);
                
                var id = entry.createElementNS("http://www.w3.org/2005/Atom", "id");
                var now = new Date();
                id.textContent = "tag:"+document.domain+","+now.getFullYear()+"-"+(now.getMonth()+1)+"-"+now.getDate()+":"+parseInt(Math.random()*100000,10);
                entry.documentElement.appendChild(id);
                
                var updated = entry.createElementNS("http://www.w3.org/2005/Atom", "updated");
                updated.textContent = now.toAtomString();
                entry.documentElement.appendChild(updated);
                
                var summaryEl = entry.createElementNS("http://www.w3.org/2005/Atom", "summary");
                summaryEl.setAttribute("type","text");
                summaryEl.textContent = summary||"";
                entry.documentElement.appendChild(summaryEl);
                
                var content = entry.createElementNS("http://www.w3.org/2005/Atom", "content");
                content.setAttribute("src",filename);
                entry.documentElement.appendChild(content);
                
                var xml = document.implementation.createLSSerializer().writeToString(entry);
                this.elements["entry"].value = xml;
            }
        }, false);
        
        document.getElementById("media-frame").addEventListener("load", function () {
            if (this.contentDocument.documentElement.localName == "entry") {
                var content = this.contentDocument.documentElement.selectSingleNode("atom:content", function () {return "http://www.w3.org/2005/Atom"});
                var uri = resolveURI(content.getAttributeNode("src"));

                var list = document.getElementById("media-list");
                var li = document.createElement("li");
                var a = document.createElement("a");
                a.href = uri;
                a.textContent = uri;
                li.appendChild(a);
                list.appendChild(li);
                
                document.getElementById("media-form").reset();
            }
        }, false);
        
        var popups = document.getElementsByClassName("popup-close");
        for (var i=0, popup; popup=popups[i]; i++) {
            popup.addEventListener("click", function (ev) {
                ev.preventDefault();
                ev.stopPropagation();
                
                ev.target.parentNode.parentNode.addClass("hidden");
            }, false);
        }
    }, false);
})();