#!/bin/bash
(
echo "LANG LINES WORDS BYTES"
for lang in 'PHP/*.php' 'C++/*.?pp' 'Java/*.java' 'Bash/*.sh' 'Python/*.py' 'Make/Makefile' 'Config/*.conf' 'Go/*.go'
do
	match=$(basename "${lang}")
	name=$(dirname "${lang}")
	echo -n "${name}: "
	find . -iname "${match}" -print0 | xargs -0 cat | wc
done
) | column -t
