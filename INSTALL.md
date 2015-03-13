1. Download latest version from http://code.google.com/p/zodeken/downloads/list

2. Place the folder Zodeken into a folder that covered by your include\_path
setting. Or simply, just place Zodeken into the same folder with your Zend
library, for example:
```
    /usr/share/php/
        Zend/
        Zodeken/
```
3. Edit your .zf.ini (usually located at your home folder), append this line:
```
    basicloader.classes.20 = "Zodeken_ZfTool_ZodekenProvider"
```
You may change the number 20 to another one that you prefer.

If don't know where that file is located, you may run:
```
    zf --setup config-file
```
The command 'zf' not found??? Read this first:
http://framework.zend.com/manual/en/zend.tool.framework.clitool.html

4. Done