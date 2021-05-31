CREATE TABLE ldap_domains (
	-- IF for domain
	domain_id serial PRIMARY KEY,

	-- Domain itself
	domain varchar(255) not null,

	-- User to which this domain belongs
	user_id integer not null

) /*$wgDBTableOptions*/;

CREATE INDEX user_id on ldap_domains (user_id);
