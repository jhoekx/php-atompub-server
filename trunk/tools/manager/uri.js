function parseURI(uri) {
    var re = new RegExp("^(([^:/?#]+):)?(//([^/?#]*))?([^?#]*)(\\?([^#]*))?(#(.*))?","g");
    
    var res = re.exec(uri);
    
    var comps = {
        scheme: res[2],
        authority: res[4],
        path: res[5],
        query: res[7],
        fragment: res[9]
    };
    
    return comps;
}

function resolveRelativeURI(uri, base_uri) {
    var R, T={};
    
    var remove_dot_segments = function (path){
        var input = path, output = [];
        
        while ( input && input[0] ) {
            if (input.indexOf("../") == 0 || input.indexOf("./") == 0 ) {
                if( input.indexOf("../") == 0 ) {
                    input = input.substr(3);
                } else {
                    input = input.substr(2);
                }
            } else if (input.indexOf("/../") == 0 || 
                        (input.indexOf("/..") == 0 && ( (input[3] && input[3] == "/") || !input[3] ) )) {
                if ( input.indexOf("/../") == 0 ) {
                    input = "/" + input.substr(4);
                } else {
                    input = "/" + input.substr(3);
                }
                
                if (output[output.length -1]) {
                    output.pop();
                }
            } else if (input.indexOf("/./") == 0 || 
                        (input.indexOf("/.") == 0 && ( (input[2] && input[2] == "/") || !input[2] ) )) {
                if ( input.indexOf("/./") == 0 ) {
                    input = "/" + input.substr(3);
                } else {
                    input = "/" + input.substr(2);
                }
            } else if ( input == "." || input == ".." ) {
                input = "";
            } else {
                if ( input.indexOf("/") == 0) {
                    input = input.substr(1);
                    
                    if ( input.indexOf("/") != -1 ) {
                        output.push( "/" + input.substring(0, input.indexOf("/")) )
                        input = input.substr(input.indexOf("/"));
                    } else {
                        output.push( "/" + input );
                        input = "";
                    }
                } else {
                    if ( input.indexOf("/") != -1 ) {
                        output.push( input.substring(0, input.indexOf("/")) )
                        input = input.substr(input.indexOf("/"));
                    } else {
                        output.push( input );
                        input = "";
                    }
                }
            }
        }
        
        return output.join("");
        
    };
    
    var merge = function (base, rel) {
        if (base.authority && !base.path) {
            if (rel.path) {
                return "/"+rel.path;
            } else {
                return "/";
            }
        } else {
            if (rel.path) {
                var slashindex = rel.path.indexOf("/");
                if (slashindex === 0) {
                    return rel.path;
                }
            } 
            if (base.path) {
                var slashindex = base.path.lastIndexOf("/");
                if ( slashindex != -1 ) {
                    return base.path.substring(0, slashindex+1)+rel.path;
                } else {
                    return rel.path;
                }
            }
        }
    };
    
    R = parseURI(uri);
    Base = parseURI(base_uri);

    if ( R.scheme ) {
        T.scheme = R.scheme;
        T.authority = R.authority;
        T.path = remove_dot_segments(R.path);
        T.query = R.query;
    } else {
        if (R.authority) {
            T.authority = R.authority;
            T.path      = remove_dot_segments(R.path);
            T.query     = R.query;
        } else {
            if (!R.path) {
                T.path = Base.path;
                if (R.query) {
                    T.query = R.query;
                } else {
                    T.query = Base.query;
                }
            } else {
                if (R.path[0] == "/") {
                    T.path = remove_dot_segments(R.path);
                } else {
                    T.path = merge(Base, R);
                    T.path = remove_dot_segments(T.path);
                }
                T.query = R.query;
            }
            T.authority = Base.authority;
        }
        T.scheme = Base.scheme;
    }
    
    T.fragment = R.fragment;

    return T;
}

function resolveURI(node, external_uri) {
    var NS = "http://www.w3.org/XML/1998/namespace";
    var merge = function (base, rel) {
        if (base.authority && !base.path) {
            if (rel.path) {
                return "/"+rel.path;
            } else {
                return "/";
            }
        } else {
            if (rel.path) {
                var slashindex = rel.path.indexOf("/");
                if (slashindex === 0) {
                    return rel.path;
                }
            }
            if (base.path) {
                var slashindex = base.path.lastIndexOf("/");
                if ( slashindex != -1 ) {
                    return base.path.substring(0, slashindex+1)+rel.path;
                } else {
                    return rel.path;
                }
            }
        }
    };
    
    
    if (!node) {return ""};
    
    var composeURI = function(comps) {
        var uri = [];
        
        if (comps.scheme) {
            uri.push(comps.scheme + "://");
        }
        
        if (comps.authority) {
            uri.push(comps.authority);
        }
        
        if (comps.path) {
            uri.push(comps.path);
        }
        
        if (comps.query) {
            uri.push(comps.query);
        }
        
        if (comps.fragment) {
            uri.push(comps.fragment);
        }
        
        return uri.join("");
    };
    
    var walk = function (element, uri) {
        //opera.postError(element+element.nodeName+" "+uri);
        if (element.nodeType != Node.ELEMENT_NODE) {return uri; }
        
        if ( element.hasAttributeNS(NS, "base") ) {
            //resolve relative uri later on
            var base = element.getAttributeNS(NS, "base");
            // absolute URI
            if ( parseURI(base).scheme ) {
                return composeURI(resolveRelativeURI(uri, base));
            }
            //opera.postError("merge: "+base+" "+uri);
            var newURI = merge(parseURI(base), parseURI(uri));

            if ( newURI.scheme ) {
                return composeURI(newURI);
            } else {
                if ( element.parentNode ) {
                    return walk(element.parentNode, newURI);
                } else {
                    // document element
                    return composeURI(newURI);
                }
            }
        } else {
            if (element.parentNode) {
                return walk(element.parentNode, uri);
            } else {
                return uri;
            }
        }
    };
    
    var uri = node.textContent;

    // absolute URI
    if ( parseURI(uri).scheme ) {
        return node.textContent;
    }
    
    if ( node.nodeType == Node.ATTRIBUTE_NODE) {
        var element = node.ownerElement;
    } else {
        var element = node;
    }
    
    //opera.postError("walk");
    var resolvedValue = walk(element, uri);
    //opera.postError(resolvedValue);
    return composeURI(resolveRelativeURI(resolvedValue, external_uri));
}

function composeURI(comps) {
    var uri = [];
        
    if (comps.scheme) {
        uri.push(comps.scheme + "://");
    }
    
    if (comps.authority) {
        uri.push(comps.authority);
    }
    
    if (comps.path) {
        uri.push(comps.path);
    }
    
    if (comps.query) {
        uri.push(comps.query);
    }
    
    if (comps.fragment) {
        uri.push(comps.fragment);
    }
    
    return uri.join("");
};