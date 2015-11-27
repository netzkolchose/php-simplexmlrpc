.PHONY: tests coverage debug full-debug doc

tests:
	phpunit

coverage:
	phpunit --coverage-html ./coverage

debug:
	phpunit --debug

full-debug:
	SERVERLOGS=1 phpunit --debug

doc:
	phpdoc -f SimpleXmlRpc.php -t phpdoc