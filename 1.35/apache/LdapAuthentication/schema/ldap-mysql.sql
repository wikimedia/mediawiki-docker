CREATE TABLE /*_*/ldap_domains (
	-- IF for domain
	domain_id int not null primary key auto_increment,

	-- Domain itself
	domain varchar(255) binary not null,

	-- User to which this domain belongs
	user_id int not null

) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/user_id on /*_*/ldap_domains (user_id);
