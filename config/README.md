# Site details metadata

`Site_Details_Index` includes every installed plugin in the SDS payload. The optional
plugin header `Update URI` is sent as `plugins[].update_uri`, taken directly from
WordPress plugin metadata rather than the update transient. Values such as the string
`"false"`, empty strings, and external URIs are preserved; missing metadata is `null`.
This does not filter plugins or change their slug, marketplace, or download URL.

Deploy [SDS #1348](https://github.com/Automattic/vip-site-details/pull/1348), including
its schema changes, before this producer change. SDS exposes the value as `updateUri`
for CCM to interpret during vulnerability scans.

Context: [PLTFRM-2576](https://linear.app/a8c/issue/PLTFRM-2576).
