---
layout: home

hero:
  name: Model Explorer for Laravel
  text: Live Eloquent model introspection for AI agents — and a browsable UI for humans.
  tagline: Zero config. Install and go. An MCP server for Claude Code, Cursor, and other agents, plus a browser UI for the rest of us.
  image:
    src: /screenshots/model-detail.png
    alt: Model Explorer for Laravel Model details
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: View on GitHub
      link: https://github.com/one-learning-community/laravel-model-explorer

features:
  - icon: 🤖
    title: AI Model Introspection (MCP)
    details: A local laravel/mcp server that lets AI coding agents introspect your models — columns, relations, scopes, accessors, and trait-correct source — without scanning your files.
  - icon: 🗂️
    title: Model List
    details: A searchable grid of every Eloquent model in your app — table name, row count, and key stats at a glance.
  - icon: 🔍
    title: Model Detail
    details: Full structure for any model — database columns with types and casts, relationships with their kinds and source, local scopes, virtual attributes, and traits.
  - icon: 📄
    title: Record Browser
    details: Look up any record by primary key or unique field. Drill into related records and lazily-resolved accessor values without leaving the browser.
  - icon: 🕸️
    title: Relationship Graph
    details: An interactive force-directed graph of every relationship across your entire model layer. Pan, zoom, drag, and click to navigate.
---
