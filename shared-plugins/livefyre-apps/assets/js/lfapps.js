var lf_extend = function(a, b) {
    for(var key in b)
        if(b.hasOwnProperty(key))
            a[key] = b[key];
    return a;
};