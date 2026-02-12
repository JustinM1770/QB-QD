package com.quickbite.app.data.api

import okhttp3.Cookie
import okhttp3.CookieJar
import okhttp3.HttpUrl

/**
 * Persiste cookies de sesión PHP (PHPSESSID) entre requests.
 * Así la app mantiene la sesión autenticada con el backend.
 */
class SessionCookieJar : CookieJar {
    private val cookieStore = mutableMapOf<String, MutableList<Cookie>>()

    override fun saveFromResponse(url: HttpUrl, cookies: List<Cookie>) {
        cookieStore[url.host] = cookies.toMutableList()
    }

    override fun loadForRequest(url: HttpUrl): List<Cookie> {
        return cookieStore[url.host] ?: emptyList()
    }

    fun clear() {
        cookieStore.clear()
    }

    fun hasSession(): Boolean {
        return cookieStore.values.flatten().any { it.name == "PHPSESSID" }
    }
}
