# Parlaparser
Parser. Yes, it's what this is. It parses stuff. Pretty impressive, I know. It crawls pages from a wonderful website of Slovenian Government, finds data about eeeeeeverything our MPs do.
```
                                       __.----.___
           ||            ||  (\(__)/)-'||      ;--` ||
          _||____________||___`(QQ)'___||______;____||_
          -||------------||----)  (----||-----------||-
          _||____________||___(o  o)___||______;____||_
          -||------------||----`--'----||-----------||-
           ||            ||       `|| ||| || ||     ||jgs
        ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
```


#Get urls
1. redne seje DZ
2. izredne seje DZ
3. seje delovnih teles

- prepare search queries, set cookie, get subpage of single session
- skip sessions in future
- check what to parse

#Get/parse data from url  

tear apart html and find parts of html
- speeches
- documents
- votes
go deeper and find related data

insert/update all data to session, append speeches, votes, documents


why we parse all sessions ? you can never know waht to expect from DZ

#Cronjobs
0 1 * * * php /mnt/web/parlaparser/importer_seje.php

0 3 * * * cd /mnt/web/parlaparser/; php get_shared_sessions.php

0 23 * * * php /mnt/web/parlaparser/parse_questions_xml.php

