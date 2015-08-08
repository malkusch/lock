# @configure_input@

doc: composer-update
	./vendor/bin/apigen generate

composer-clean:
	rm -rf vendor/malkusch/lock/ composer.lock

composer-update: composer-clean
	composer.phar update
