if (window.pendo && window.pendo.apiKey === "c04bb66e-a34f-4b7c-6907-c9dbea86209b") {
    pendo.initialize({
        visitor: {
            id: vip_pendo.visitor.id,
            full_name: vip_pendo.visitor.full_name,
            role: vip_pendo.visitor.role,
        },
        account: {
            id: account.id,
        }
    });
}
