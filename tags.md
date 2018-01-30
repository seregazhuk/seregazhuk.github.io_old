---
title: Tags
layout: default
permalink: /tags
---


<h2>Posts By Categories</h2>

<div class="content-grid-info">
    {% capture site_tags %}{% for tag in site.tags %}{{ tag[1].size }}#{{ tag | first | downcase }}#{{ tag | first }}{% unless forloop.last %},{% endunless %}{% endfor %}{% endcapture %}
    {% assign tag_hashes = site_tags | split:',' | sort %}
    
    <ul class="list-group all-tags">
    {% for hash in tag_hashes %}
      {% assign keyValue = hash | split: '#' %}
      {% capture tag_word %}{{ keyValue[2] | strip_newlines }}{% endcapture %}
      <li class="list-group-item">
        <a href="/tags/{{ tag_word }}">
          {{ tag_word }}
          <span class="badge pull-right">{{ site.tags[tag_word].size }}</span>
        </a>
      </li>
    {% endfor %}
    </ul>
</div>
