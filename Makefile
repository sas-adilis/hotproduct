.PHONY: prepare

noblank:
	find . -type f -name "*.php" ! -path "./vendor/*" | while read file; do \
		awk ' \
			BEGIN { line = 0; in_doc = 0; after_doc = 0 } \
			{ \
				line++; \
				if (line == 1 && $$0 ~ /^<\?php[[:space:]]*$$/) { print; next } \
				if (line == 2 && $$0 ~ /^[[:space:]]*$$/) { next } \
				if (line <= 10 && $$0 ~ /^\/\*\*/) { in_doc = 1; print; next } \
				if (in_doc) { \
					print; \
					if ($$0 ~ /\*\//) { in_doc = 0; after_doc = 1; next } \
					else next; \
				} \
				if (after_doc && $$0 ~ /^[[:space:]]*$$/) { next } \
				{ print; after_doc = 0 } \
			} \
		' "$$file" > "$$file.tmp" && mv "$$file.tmp" "$$file" && echo "✔ Modifié : $$file"; \
	done

fix :
	composer i && \
	PHP_CS_FIXER_IGNORE_ENV=1 php vendor/bin/php-cs-fixer fix && \
	composer i --no-dev && \
	rm -rf vendor && \
	$(MAKE) noblank
